import 'dart:convert';
import 'dart:io';
import 'package:bolidemarket/core/api.dart';
import 'package:bolidemarket/core/models.dart';
import 'package:bolidemarket/features/commerce/models.dart';
import 'package:bolidemarket/features/commerce/pdf_files.dart';
import 'package:bolidemarket/features/commerce/repository.dart';
import 'package:bolidemarket/features/realtime/reverb.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/services.dart';
import 'core_test.dart' show Adapter;
import 'commerce_support.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  test('Modes DEMO exacts et aucune donnée bancaire', () {
    expect(DemoPayment.values.map((p) => p.value), [
      'MOBILE_MONEY_DEMO',
      'CARD_DEMO',
      'CASH_DEMO',
      'BANK_TRANSFER_DEMO',
    ]);
    expect(reservationRequest('11', DemoPayment.card), {
      'quote_id': '11',
      'payment_method': 'CARD_DEMO',
    });
    final order = orderRequest('7', DemoPayment.cash, {'mode': 'self'});
    expect(order, {
      'vehicle_id': '7',
      'payment_method': 'CASH_DEMO',
      'handover': {'mode': 'self'},
    });
    for (final forbidden in [
      'total',
      'price',
      'currency',
      'cvv',
      'card_number',
    ]) {
      expect(order.containsKey(forbidden), false);
    }
  });
  test('Montant du résumé et snapshot reçu : aucune conversion/recalcul', () {
    expect(CommerceRecord(recordJson()).total.format(), '180 000 FCFA');
    final receipt = Receipt(receiptJson());
    expect(receipt.total.format(), '1 800,50 €');
    expect(receipt.buyer['name'], 'Client historique');
    expect(receipt.transaction['days'], 4);
  });
  test('Clé par intention sûre, stable quand réutilisée', () {
    final a = newIntentKey(), b = newIntentKey();
    expect(a, isNotNull);
    expect(a, isNot(b));
    expect(a, matches(RegExp(r'^[A-Za-z0-9_-]{8,80}$')));
  });
  test(
    'Dates demi-ouvertes, chevauchement, jours passés et fin de fenêtre',
    () {
      final now = DateTime.utc(2026, 10, 9, 12);
      final availability = Availability({
        'timezone': 'Africa/Abidjan',
        'is_for_rent': true,
        'inventory_status': 'available',
        'from': '2026-10-09T00:00:00Z',
        'to': '2027-10-09T00:00:00Z',
        'intervals': [
          {
            'starts_at': '2026-10-12T00:00:00Z',
            'ends_at': '2026-10-15T00:00:00Z',
          },
        ],
      });
      expect(
        availability.validRange(
          DateTime.utc(2026, 10, 10),
          DateTime.utc(2026, 10, 12),
          now: now,
        ),
        true,
      );
      expect(
        availability.validRange(
          DateTime.utc(2026, 10, 11),
          DateTime.utc(2026, 10, 13),
          now: now,
        ),
        false,
      );
      expect(availability.dayFree(DateTime.utc(2026, 10, 12), now: now), false);
      expect(availability.dayFree(DateTime.utc(2026, 10, 15), now: now), true);
      expect(availability.dayFree(DateTime.utc(2026, 10, 8), now: now), false);
      expect(
        availability.validRange(
          DateTime.utc(2026, 10, 10),
          DateTime.utc(2026, 10, 10),
          now: now,
        ),
        false,
      );
      expect(
        availability.validRange(
          DateTime.utc(2027, 10, 8),
          DateTime.utc(2027, 10, 10),
          now: now,
        ),
        false,
      );
    },
  );
  test(
    'Hold expiré libéré visuellement, bloc sans fin et inventaire indisponible',
    () {
      final base = openAvailability().json;
      final day = shopToday('Africa/Abidjan').add(const Duration(days: 2));
      final availability = Availability({
        ...base,
        'intervals': [
          {
            'starts_at': day.toIso8601String(),
            'ends_at': null,
            'expires_at': DateTime.now()
                .subtract(const Duration(minutes: 1))
                .toIso8601String(),
          },
        ],
      });
      expect(availability.dayFree(day), true);
      expect(
        Availability({
          ...base,
          'intervals': [
            {'starts_at': day.toIso8601String(), 'ends_at': null},
          ],
        }).dayFree(day),
        false,
      );
      expect(
        Availability({...base, 'inventory_status': 'rented'}).dayFree(day),
        false,
      );
    },
  );
  test('Fuseau boutique et changement DST, jamais le fuseau du téléphone', () {
    expect(
      shopToday('America/Los_Angeles', now: DateTime.utc(2026, 10, 10, 2)),
      DateTime.utc(2026, 10, 9),
    );
    expect(
      shopMidnight(DateTime.utc(2026, 3, 30), 'Europe/Paris')
          .difference(shopMidnight(DateTime.utc(2026, 3, 29), 'Europe/Paris'))
          .inHours,
      23,
    );
    expect(() => shopZone('Unknown/Zone'), throwsA(isA<Exception>()));
  });
  test('Annulation visible seulement pending ou confirmed avant départ', () {
    final record = CommerceRecord(recordJson(status: 'confirmed'));
    expect(record.canCancelAt(DateTime.now()), true);
    expect(
      record.canCancelAt(DateTime.now().add(const Duration(days: 20))),
      false,
    );
    expect(
      CommerceRecord(recordJson(status: 'active')).canCancelAt(DateTime.now()),
      false,
    );
  });
  test('409 conserve code serveur et message métier spécifique', () {
    final error = DioException(
      requestOptions: RequestOptions(path: '/reservations'),
      response: Response(
        requestOptions: RequestOptions(path: '/reservations'),
        statusCode: 409,
        data: {
          'error': {'code': 'VEHICLE_UNAVAILABLE'},
        },
      ),
    );
    final failure = ApiFailure.from(error);
    expect(failure.code, 'VEHICLE_UNAVAILABLE');
    expect(
      commerceError(error, rental: true),
      'Ces dates viennent de devenir indisponibles.',
    );
    expect(commerceError(error, rental: false), contains('plus disponible'));
    expect(
      commerceError(
        const ApiFailure('Conflit', status: 409, code: 'QUOTE_EXPIRED'),
        rental: true,
      ),
      contains('expiré'),
    );
  });
  test(
    'POST réel : clé Idempotency et Bearer, payload sans montant client',
    () async {
      RequestOptions? sent;
      final dio = Dio(BaseOptions(baseUrl: 'http://localhost/api/v1'))
        ..httpClientAdapter = Adapter((request) async {
          sent = request;
          return ResponseBody.fromString(
            jsonEncode({'data': recordJson()}),
            201,
            headers: {
              'content-type': ['application/json'],
            },
          );
        });
      final api = ApiClient(client: dio)..token = 'test-session';
      await CommerceRepository(
        api,
      ).reserve(reservationRequest('11', DemoPayment.card), 'same-intent-123');
      expect(sent!.headers['Idempotency-Key'], 'same-intent-123');
      expect(sent!.headers['Authorization'], 'Bearer test-session');
      expect(sent!.data, {'quote_id': '11', 'payment_method': 'CARD_DEMO'});
    },
  );
  test(
    'Calendrier charge toutes les pages, pas seulement les 100 premiers blocs',
    () async {
      var calls = 0;
      final dio = Dio(BaseOptions(baseUrl: 'http://localhost/api/v1'))
        ..httpClientAdapter = Adapter((request) async {
          calls++;
          return ResponseBody.fromString(
            jsonEncode({
              'data': {
                ...openAvailability().json,
                'intervals': [
                  {
                    'starts_at': '2026-10-12T00:00:00Z',
                    'ends_at': '2026-10-13T00:00:00Z',
                  },
                ],
              },
              'meta': {'last_page': 2},
            }),
            200,
            headers: {
              'content-type': ['application/json'],
            },
          );
        });
      final result = await CommerceRepository(
        ApiClient(client: dio),
      ).availability('qa');
      expect(calls, 2);
      expect(result.intervals.length, 2);
    },
  );
  test('PDF privé : en-tête Accept/Bearer, contenu invalide refusé', () async {
    var pdf = false;
    RequestOptions? sent;
    final dio = Dio(BaseOptions(baseUrl: 'http://localhost/api/v1'))
      ..httpClientAdapter = Adapter((request) async {
        sent = request;
        return ResponseBody.fromBytes(
          (pdf ? '%PDF-1.4\n' : 'not a pdf').codeUnits,
          200,
          headers: {
            'content-type': ['application/pdf'],
          },
        );
      });
    final repo = ReceiptRepository(
      ApiClient(client: dio)..token = 'private-session',
    );
    await expectLater(repo.pdf('BM-RCP-2026-QA'), throwsA(isA<ApiFailure>()));
    pdf = true;
    expect(await repo.pdf('BM-RCP-2026-QA'), isNotEmpty);
    expect(sent!.headers['Accept'], 'application/pdf');
    expect(sent!.headers['Authorization'], 'Bearer private-session');
    expect(sent!.followRedirects, false);
  });
  test(
    'PDF app documents, purge limitée et refus partage après changement session',
    () async {
      final root = await Directory.systemTemp.createTemp(
        'bolidemarket-pdf-test-',
      );
      addTearDown(() async {
        final allowed = Directory.systemTemp.absolute.path;
        if (!root.absolute.path.startsWith(allowed)) {
          throw StateError('Test directory hors périmètre');
        }
        await root.delete(recursive: true);
      });
      String? token = 'first';
      final service = PdfFiles(
        FakeReceipts(),
        () => token,
        directory: () async => root,
      );
      final file = await service.download('BM-RCP-2026-QA');
      expect(await file.readAsString(), startsWith('%PDF-'));
      const channel = MethodChannel('dev.fluttercommunity.plus/share');
      MethodCall? nativeCall;
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(channel, (call) async {
            nativeCall = call;
            return 'dev.fluttercommunity.plus/share/unavailable';
          });
      addTearDown(
        () => TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
            .setMockMethodCallHandler(channel, null),
      );
      await service.share(file, const Rect.fromLTWH(5, 5, 20, 20));
      expect(nativeCall!.method, 'share');
      expect((nativeCall!.arguments as Map)['paths'], [file.path]);
      expect((nativeCall!.arguments as Map)['mimeTypes'], ['application/pdf']);
      expect(
        (nativeCall!.arguments as Map).containsKey('Authorization'),
        false,
      );
      final unrelated = File('${file.parent.path}/keep.txt');
      await unrelated.writeAsString('keep');
      token = 'second';
      await expectLater(
        service.share(file, const Rect.fromLTWH(0, 0, 20, 20)),
        throwsA(isA<ApiFailure>()),
      );
      await service.clear();
      expect(await file.exists(), false);
      expect(await unrelated.exists(), true);
      await expectLater(
        service.download('../../invalid'),
        throwsA(isA<ApiFailure>()),
      );
    },
  );
  test(
    'Realtime : mapping, canal privé exact et aucune transaction publique',
    () {
      Json frame(String event, String channel) => {
        'event': event,
        'channel': channel,
        'data': jsonEncode({
          'event_id': 'uuid-1',
          'type': event,
          'data': {'id': '12', 'status': 'confirmed'},
        }),
      };
      expect(
        DomainSignal.decode(
          frame('ReservationConfirmed', 'private-user.1'),
          '1',
        )!.type,
        'ReservationConfirmed',
      );
      expect(
        DomainSignal.decode(
          frame('ReservationConfirmed', 'private-user.2'),
          '1',
        ),
        isNull,
      );
      expect(
        DomainSignal.decode(frame('ReservationConfirmed', 'marketplace'), '1'),
        isNull,
      );
      expect(
        DomainSignal.decode(
          frame('VehicleStatusChanged', 'marketplace'),
          null,
        )!.type,
        'VehicleStatusChanged',
      );
      expect(DomainSignal.decode(frame('Unknown', 'marketplace'), '1'), isNull);
    },
  );
}
