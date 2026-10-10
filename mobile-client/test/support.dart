import 'package:bolidemarket/core/api.dart';
import 'package:bolidemarket/core/models.dart';
import 'package:bolidemarket/core/providers.dart';
import 'package:bolidemarket/core/theme.dart';
import 'package:bolidemarket/features/auth/auth_repository.dart';
import 'package:bolidemarket/features/marketplace/repositories.dart';
import 'package:bolidemarket/features/marketplace/query.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

final testUser = AppUser({
  'id': '1',
  'first_name': 'Aya',
  'last_name': 'Demo',
  'email': 'aya@example.test',
  'phone': '+2250701234567',
  'country_code': 'CI',
  'country': {'name': 'Côte d’Ivoire', 'currency_code': 'XOF'},
  'city_id': '1',
  'city': {'name': 'Abidjan'},
  'demo_mode': true,
  'email_verification_required': false,
});
Vehicle sample({bool rental = false, bool sold = false}) => Vehicle({
  'id': '1',
  'slug': 'toyota-rav4',
  'brand': {'name': 'Toyota'},
  'model': {'name': 'RAV4'},
  'year': 2024,
  'fuel': 'petrol',
  'transmission': 'automatic',
  'inventory_status': sold ? 'sold' : 'available',
  'offer_types': [rental ? 'rent' : 'sale'],
  'is_demo': true,
  if (!rental)
    'sale_price': {
      'amount_minor': '18500000',
      'currency': 'XOF',
      'minor_unit': 0,
    },
  if (rental)
    'rental_daily_price': {
      'amount_minor': '45000',
      'currency': 'XOF',
      'minor_unit': 0,
    },
  'location': {
    'country': {'code': 'CI', 'name': 'Côte d’Ivoire'},
    'city': {'name': 'Abidjan'},
  },
  'shop': {'slug': 'demo-shop', 'name': 'Demo Shop'},
  'images': [],
  'features': [],
  'description': 'Véhicule de test.',
});
final testCountries = <Json>[
  {
    'code': 'CI',
    'name': 'Côte d’Ivoire',
    'phone_code': '+225',
    'currency_code': 'XOF',
  },
  {'code': 'FR', 'name': 'France', 'phone_code': '+33', 'currency_code': 'EUR'},
];

class MemoryStore implements SessionStore {
  String? token;
  bool seen = true;
  @override
  Future<String?> readToken() async => token;
  @override
  Future<void> writeToken(String value) async {
    token = value;
  }

  @override
  Future<void> deleteToken() async {
    token = null;
  }

  @override
  Future<bool> onboardingSeen() async => seen;
  @override
  Future<void> markOnboarding() async {
    seen = true;
  }
}

class FakeAuth extends AuthRepository {
  FakeAuth(super.api, super.store);
  Object? failure;
  Json? registered;
  @override
  Future<AppUser> me() async {
    if (failure != null) throw failure!;
    return testUser;
  }

  @override
  Future<AppUser> login(String email, String password) async {
    if (failure != null) throw failure!;
    api.token = 'fake-token';
    await store.writeToken('fake-token');
    return testUser;
  }

  @override
  Future<void> register(Json fields) async {
    registered = fields;
  }

  @override
  Future<void> logout() async {
    api.token = null;
    await store.deleteToken();
  }
}

class FakeVehicles extends VehicleRepository {
  FakeVehicles() : super(ApiClient());
  List<Vehicle> items = [sample()];
  List<Json> requests = [];
  Object? failure;
  @override
  Future<PageData<Vehicle>> search(
    SearchQuery query, {
    int page = 1,
    String? shop,
  }) async {
    requests.add(query.parameters(page: page));
    if (failure != null) throw failure!;
    return PageData(items, page, 1, items.length);
  }

  @override
  Future<Vehicle> detail(String slug) async => items.first;
  @override
  Future<List<Json>> references(String kind, [Json params = const {}]) async =>
      kind == 'countries' ? testCountries : [];
}

class FakeAccount extends AccountRepository {
  FakeAccount() : super(ApiClient());
  Json? saved;
  @override
  Future<AppUser> update(Json fields) async {
    saved = fields;
    return testUser;
  }

  @override
  Future<AppUser> avatar(List<int> bytes, String name) async => testUser;
}

Future<ProviderContainer> screen(
  WidgetTester tester,
  Widget widget, {
  bool loggedIn = true,
  List<Override> overrides = const [],
  double width = 390,
  bool french = false,
}) async {
  tester.view.physicalSize = Size(width, 1000);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final auth = FakeAuth(ApiClient(), MemoryStore());
  final router = GoRouter(
    routes: [
      GoRoute(
        path: '/',
        builder: (_, _) => Scaffold(body: widget),
      ),
      GoRoute(
        path: '/login',
        builder: (_, _) => const Scaffold(body: Text('Destination connexion')),
      ),
      GoRoute(
        path: '/account',
        builder: (_, _) => const Scaffold(body: Text('Destination compte')),
      ),
      GoRoute(
        path: '/home',
        builder: (_, _) => const Scaffold(body: Text('Destination accueil')),
      ),
      GoRoute(
        path: '/marketplace',
        builder: (_, _) => const Scaffold(body: Text('Destination marché')),
      ),
      GoRoute(
        path: '/vehicle/:slug',
        builder: (_, _) => const Scaffold(body: Text('Destination véhicule')),
      ),
    ],
  );
  addTearDown(router.dispose);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        authRepositoryProvider.overrideWithValue(auth),
        authProvider.overrideWith((ref) {
          final controller = AuthController(auth);
          if (loggedIn) {
            controller.setUser(testUser);
          } else {
            controller.invalidate();
          }
          return controller;
        }),
        countriesProvider.overrideWith((ref) async => testCountries),
        configProvider.overrideWith(
          (ref) async => {'default_country': 'CI', 'demo_mode': true},
        ),
        favoritesProvider.overrideWith((ref) async => []),
        ...overrides,
      ],
      child: MaterialApp.router(
        theme: appTheme(),
        routerConfig: router,
        locale: french ? const Locale('fr') : null,
        supportedLocales: french ? const [Locale('fr')] : const [Locale('en')],
        localizationsDelegates: french
            ? GlobalMaterialLocalizations.delegates
            : null,
      ),
    ),
  );
  await tester.pump();
  return ProviderScope.containerOf(tester.element(find.byType(MaterialApp)));
}
