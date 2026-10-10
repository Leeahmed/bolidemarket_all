import 'dart:async';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:bolidemarket/core/budget.dart';
import 'package:bolidemarket/core/api.dart';
import 'package:bolidemarket/core/models.dart';
import 'package:bolidemarket/core/phone.dart';
import 'package:bolidemarket/core/providers.dart';
import 'package:bolidemarket/features/auth/auth_screen.dart';
import 'package:bolidemarket/features/marketplace/query.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'support.dart';

class Adapter implements HttpClientAdapter {
  Adapter(this.respond);
  final Future<ResponseBody> Function(RequestOptions) respond;
  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) => respond(options);
  @override
  void close({bool force = false}) {}
}

void main() {
  test('Budget EUR : unités majeures vers entier sans flottant', () {
    expect(budgetMinor('28 900,50', 2), '2890050');
    expect(budgetMajor('2890050', 2), '28900.50');
    expect(budgetMinor('18500000', 0), '18500000');
    expect(() => budgetMinor('1.2', 0), throwsFormatException);
  });
  test('JSON 2xx invalide ne fournit pas une fausse identité', () async {
    final dio = Dio(BaseOptions(baseUrl: 'http://test.invalid'))
      ..httpClientAdapter = Adapter(
        (_) async => ResponseBody.fromString('not json', 200),
      );
    final api = ApiClient(client: dio)..token = 'saved';
    await expectLater(api.get('/auth/me'), throwsA(isA<ApiFailure>()));
    expect(api.token, 'saved');
  });
  group('Montants exacts et devises', () {
    for (final row in [
      ('18500000', 'XOF', 0, '18 500 000 FCFA'),
      ('2890000', 'EUR', 2, '28 900 €'),
      ('3290000', 'USD', 2, '\$32,900'),
      ('4100000', 'CAD', 2, 'CA\$41,000'),
      ('2890050', 'EUR', 2, '28 900,50 €'),
    ]) {
      test(
        row.$2 + row.$1,
        () => expect(
          Money(BigInt.parse(row.$1), row.$2, row.$3).format(),
          row.$4,
        ),
      );
    }
  });
  test(
    'Téléphone CI national vers E.164',
    () => expect(normalizePhone('0701234567', 'CI'), '+2250701234567'),
  );
  test(
    'Téléphone France national vers E.164',
    () => expect(normalizePhone('0612345678', 'FR'), '+33612345678'),
  );
  test(
    'Téléphone mauvais pays refusé',
    () => expect(
      () => normalizePhone('+33612345678', 'CI'),
      throwsFormatException,
    ),
  );
  test('Filtres gardent GPS zéro et booléen false', () {
    final params = const SearchQuery(
      values: {
        'latitude': 0,
        'longitude': 0,
        'is_certified': 0,
        'listing_type': 'rental',
        'currency': 'XOF',
        'min_price': 45000,
        'sort': 'price_asc',
      },
    ).parameters(page: 2);
    expect(params['latitude'], 0);
    expect(params['is_certified'], 0);
    expect(params['page'], 2);
    expect(params['listing_type'], 'rental');
  });
  test(
    'Prix exige intention et devise',
    () => expect(
      () => const SearchQuery(values: {'sort': 'price_asc'}).parameters(),
      throwsFormatException,
    ),
  );
  test('Distance exige GPS, popular non supporté', () {
    expect(
      () => const SearchQuery(values: {'sort': 'distance'}).parameters(),
      throwsFormatException,
    );
    expect(
      () => const SearchQuery(values: {'sort': 'popular'}).parameters(),
      throwsFormatException,
    );
  });
  test('Retour login ne permet pas de destination externe', () {
    expect(safeDestination('//example.com'), '/account');
    expect(safeDestination('/vehicle/rav4'), '/vehicle/rav4');
  });
  for (final status in [401, 403, 409, 422, 500]) {
    test('Erreur utilisateur $status', () {
      final error = DioException(
        requestOptions: RequestOptions(path: '/test'),
        response: Response(
          requestOptions: RequestOptions(path: '/test'),
          statusCode: status,
          data: {
            'error': {
              'message': 'Vérifiez le champ.',
              'fields': {
                'email': ['Invalide'],
              },
            },
          },
        ),
      );
      expect(ApiFailure.from(error).status, status);
      expect(ApiFailure.from(error).message, isNot(contains('DioException')));
      if (status == 422) {
        expect(ApiFailure.from(error).fields['email'], ['Invalide']);
      }
    });
  }
  test(
    'Erreur réseau propre',
    () => expect(
      ApiFailure.from(
        DioException(requestOptions: RequestOptions(path: '/test')),
      ).message,
      'Impossible de se connecter à BolideMarket.',
    ),
  );
  test('Restauration token + identité serveur', () async {
    final store = MemoryStore()..token = 'saved';
    final auth = AuthController(FakeAuth(ApiClient(), store));
    addTearDown(auth.dispose);
    await auth.restore();
    expect(auth.state.valueOrNull?.id, '1');
    expect(auth.repository.api.token, 'saved');
  });
  test('Panne réseau ne détruit pas le token', () async {
    final store = MemoryStore()..token = 'saved';
    final repo = FakeAuth(ApiClient(), store)
      ..failure = const ApiFailure('Hors ligne');
    final auth = AuthController(repo);
    addTearDown(auth.dispose);
    await auth.restore();
    expect(auth.state.hasError, true);
    expect(store.token, 'saved');
  });
  test('401 invalide efface la session', () async {
    final store = MemoryStore()..token = 'saved';
    final repo = FakeAuth(ApiClient(), store)
      ..failure = const ApiFailure('Session expirée', status: 401);
    final auth = AuthController(repo);
    addTearDown(auth.dispose);
    await auth.restore();
    expect(auth.state.valueOrNull, null);
    expect(store.token, null);
  });
  test('Logout révoque et supprime le token', () async {
    final store = MemoryStore()..token = 'saved';
    final auth = AuthController(FakeAuth(ApiClient(), store));
    addTearDown(auth.dispose);
    await auth.restore();
    await auth.logout();
    expect(store.token, null);
    expect(auth.state.valueOrNull, null);
  });
  test('Interceptor injecte Bearer et ignore ancien 401', () async {
    final dio = Dio(BaseOptions(baseUrl: 'http://test.invalid'));
    final pending = Completer<ResponseBody>();
    dio.httpClientAdapter = Adapter((options) {
      expect(options.headers['Authorization'], 'Bearer old');
      return pending.future;
    });
    final api = ApiClient(client: dio)..token = 'old';
    var invalidated = 0;
    api.onInvalidSession = () => invalidated++;
    final response = api.get('/auth/me');
    final assertion = expectLater(response, throwsA(isA<DioException>()));
    await Future<void>.delayed(Duration.zero);
    api.token = 'new';
    pending.complete(
      ResponseBody.fromString(
        '{}',
        401,
        headers: {
          'content-type': ['application/json'],
        },
      ),
    );
    await assertion;
    expect(invalidated, 0);
  });
  test('Interceptor 401 courant invalide une fois', () async {
    final dio = Dio(BaseOptions(baseUrl: 'http://test.invalid'))
      ..httpClientAdapter = Adapter(
        (_) async => ResponseBody.fromString('{}', 401),
      );
    final api = ApiClient(client: dio)..token = 'current';
    var invalidated = 0;
    api.onInvalidSession = () {
      invalidated++;
      api.token = null;
    };
    await expectLater(api.get('/auth/me'), throwsA(isA<DioException>()));
    expect(invalidated, 1);
  });
  test('Pagination protège le double chargement', () async {
    final repo = FakeVehicles();
    final controller = CatalogueController(repo, const SearchQuery());
    addTearDown(controller.dispose);
    await controller.reload();
    await Future.wait([controller.more(), controller.more()]);
    expect(repo.requests.length, 1);
  });
}
