import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'api.dart';
import 'models.dart';
import '../features/auth/auth_repository.dart';
import '../features/marketplace/repositories.dart';
import '../features/marketplace/query.dart';
import '../features/location/location_repository.dart';

final apiProvider = Provider((ref) => ApiClient());
final sessionStoreProvider = Provider<SessionStore>(
  (ref) => const SecureSessionStore(),
);
final authRepositoryProvider = Provider(
  (ref) =>
      AuthRepository(ref.watch(apiProvider), ref.watch(sessionStoreProvider)),
);
final vehiclesProvider = Provider(
  (ref) => VehicleRepository(ref.watch(apiProvider)),
);
final shopsProvider = Provider((ref) => ShopRepository(ref.watch(apiProvider)));
final favoriteRepositoryProvider = Provider(
  (ref) => FavoriteRepository(ref.watch(apiProvider)),
);
final accountRepositoryProvider = Provider(
  (ref) => AccountRepository(ref.watch(apiProvider)),
);
final locationRepositoryProvider = Provider((ref) => LocationRepository());
final authProvider =
    StateNotifierProvider<AuthController, AsyncValue<AppUser?>>(
      (ref) => AuthController(ref.watch(authRepositoryProvider)),
    );

class AuthController extends StateNotifier<AsyncValue<AppUser?>> {
  AuthController(this.repository) : super(const AsyncLoading()) {
    repository.api.onInvalidSession = invalidate;
  }
  final AuthRepository repository;
  bool onboarding = true;
  int revision = 0;
  Future<void> restore() async {
    final current = ++revision;
    state = const AsyncLoading();
    try {
      onboarding = !await repository.store.onboardingSeen();
      repository.api.token = await repository.store.readToken();
      final user = repository.api.token == null ? null : await repository.me();
      if (current == revision) state = AsyncData(user);
    } catch (e, st) {
      if (ApiFailure.from(e).status == 401 && current == revision) {
        await invalidate();
      } else if (current == revision) {
        state = AsyncError(ApiFailure.from(e), st);
      }
    }
  }

  Future<void> invalidate() async {
    revision++;
    repository.api.token = null;
    await repository.store.deleteToken();
    if (mounted) state = const AsyncData(null);
  }

  Future<void> login(String email, String password) async {
    revision++;
    final user = await repository.login(email, password);
    state = AsyncData(user);
  }

  void setUser(AppUser user) {
    revision++;
    state = AsyncData(user);
  }

  Future<void> logout() async {
    await repository.logout();
    revision++;
    state = const AsyncData(null);
  }
}

final configProvider = FutureProvider(
  (ref) => ref.watch(vehiclesProvider).config(),
);
final countriesProvider = FutureProvider(
  (ref) => ref.watch(vehiclesProvider).references('countries'),
);
final locationProvider = StateProvider<Json>((ref) => {});
final locationLabelProvider = StateProvider<String?>((ref) => null);
final contextProvider = Provider<Json>((ref) {
  final location = ref.watch(locationProvider);
  if (location.isNotEmpty) return location;
  final user = ref.watch(authProvider).valueOrNull;
  final country = user?.country.isNotEmpty == true
      ? user!.country
      : ref.watch(configProvider).valueOrNull?['default_country'];
  return {
    'location_mode': 'rank',
    'country_code': ?country,
    if (user?.json['city_id'] != null) 'city_id': user!.json['city_id'],
    if (user?.json['district_id'] != null)
      'district_id': user!.json['district_id'],
  };
});
final homeProvider = FutureProvider((ref) async {
  final repo = ref.watch(vehiclesProvider);
  final context = ref.watch(contextProvider);
  final results = await Future.wait<dynamic>([
    repo.search(SearchQuery(values: {...context, 'status': 'available'})),
    repo.search(SearchQuery(values: {...context, 'sort': 'newest'})),
    ref.watch(shopsProvider).list(),
  ]);
  return HomeData(
    results[0] as PageData<Vehicle>,
    results[1] as PageData<Vehicle>,
    results[2] as List<Json>,
  );
});

class HomeData {
  HomeData(this.nearby, this.newest, this.shops);
  final PageData<Vehicle> nearby, newest;
  final List<Json> shops;
}

final searchQueryProvider = StateProvider<SearchQuery>(
  (ref) => SearchQuery(values: ref.watch(contextProvider)),
);
final catalogueProvider =
    StateNotifierProvider.autoDispose<
      CatalogueController,
      AsyncValue<PageData<Vehicle>>
    >((ref) {
      final controller = CatalogueController(
        ref.watch(vehiclesProvider),
        ref.watch(searchQueryProvider),
      );
      controller.reload();
      return controller;
    });

class CatalogueController extends StateNotifier<AsyncValue<PageData<Vehicle>>> {
  CatalogueController(this.repo, this.query, {this.shop})
    : super(const AsyncLoading());
  final VehicleRepository repo;
  final SearchQuery query;
  final String? shop;
  bool loadingMore = false;
  Future<void> reload() async {
    state = const AsyncLoading();
    try {
      final data = await repo.search(query, shop: shop);
      if (mounted) state = AsyncData(data);
    } catch (e, st) {
      if (mounted) {
        state = AsyncError(
          e is FormatException ? ApiFailure(e.message) : ApiFailure.from(e),
          st,
        );
      }
    }
  }

  Future<void> more() async {
    final data = state.valueOrNull;
    if (data == null || !data.hasMore || loadingMore) return;
    loadingMore = true;
    try {
      final next = await repo.search(query, page: data.page + 1, shop: shop);
      if (mounted) {
        state = AsyncData(
          PageData(
            [...data.items, ...next.items],
            next.page,
            next.lastPage,
            next.total,
          ),
        );
      }
    } finally {
      loadingMore = false;
    }
  }
}

final vehicleProvider = FutureProvider.autoDispose.family<Vehicle, String>(
  (ref, slug) => ref.watch(vehiclesProvider).detail(slug),
);
final shopProvider = FutureProvider.autoDispose.family<Json, String>(
  (ref, slug) => ref.watch(shopsProvider).detail(slug),
);
final favoritesProvider = FutureProvider<List<Vehicle>>((ref) async {
  final user = ref.watch(authProvider).valueOrNull;
  if (user == null) return [];
  return ref.watch(favoriteRepositoryProvider).all();
});
final accountProvider = FutureProvider<Json>((ref) async {
  final user = ref.watch(authProvider).valueOrNull;
  if (user == null) return {};
  return ref.watch(accountRepositoryProvider).overview();
});

final accountSuggestionsProvider = FutureProvider<List<Vehicle>>((ref) async {
  final context = ref.watch(contextProvider);
  final result = await ref
      .watch(vehiclesProvider)
      .search(SearchQuery(values: {...context, 'status': 'available'}));
  return result.items.take(3).toList();
});
