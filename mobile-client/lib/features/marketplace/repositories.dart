import 'package:dio/dio.dart';
import '../../core/api.dart';
import '../../core/models.dart';
import 'query.dart';

class VehicleRepository {
  VehicleRepository(this.api);
  final ApiClient api;
  Future<PageData<Vehicle>> search(
    SearchQuery query, {
    int page = 1,
    String? shop,
  }) async => PageData.fromJson(
    await api.get(
      shop == null
          ? '/vehicles'
          : '/shops/${Uri.encodeComponent(shop)}/vehicles',
      query.parameters(page: page),
    ),
    Vehicle.new,
  );
  Future<Vehicle> detail(String slug) async => Vehicle(
    object((await api.get('/vehicles/${Uri.encodeComponent(slug)}'))['data']),
  );
  Future<List<Json>> references(String kind, [Json params = const {}]) async =>
      ((await api.get('/$kind', params))['data'] as List).map(object).toList();
  Future<Json> config() async => object((await api.get('/app-config'))['data']);
}

class ShopRepository {
  ShopRepository(this.api);
  final ApiClient api;
  Future<List<Json>> list() async =>
      ((await api.get('/shops', {'per_page': 6}))['data'] as List)
          .map(object)
          .toList();
  Future<Json> detail(String slug) async =>
      object((await api.get('/shops/${Uri.encodeComponent(slug)}'))['data']);
}

class FavoriteRepository {
  FavoriteRepository(this.api);
  final ApiClient api;
  Future<PageData<Vehicle>> list({int page = 1}) async => PageData.fromJson(
    await api.get('/me/favorites', {'page': page, 'per_page': 100}),
    Vehicle.new,
  );
  Future<List<Vehicle>> all() async {
    final rows = <Vehicle>[];
    var page = 1;
    while (true) {
      final result = await list(page: page++);
      rows.addAll(result.items);
      if (!result.hasMore) return rows;
    }
  }

  Future<void> set(String id, bool favorite) async {
    if (favorite) {
      await api.dio.put<dynamic>('/me/favorites/$id');
    } else {
      await api.dio.delete<dynamic>('/me/favorites/$id');
    }
  }
}

class AccountRepository {
  AccountRepository(this.api);
  final ApiClient api;
  Future<AppUser> update(Json fields) async => AppUser(
    object(
      object(
        (await api.dio.patch<dynamic>('/me/profile', data: fields)).data,
      )['data'],
    ),
  );
  Future<AppUser> avatar(List<int> bytes, String name) async => AppUser(
    object(
      object(
        (await api.dio.post<dynamic>(
          '/me/avatar',
          data: FormData.fromMap({
            'avatar': MultipartFile.fromBytes(bytes, filename: name),
          }),
        )).data,
      )['data'],
    ),
  );
  Future<List<Json>> saleOrders() async {
    final rows = <Json>[];
    var page = 1;
    while (true) {
      final result = await api.get('/me/orders', {
        'page': page,
        'per_page': 100,
      });
      rows.addAll(
        (result['data'] as List).map(object).where((r) => r['kind'] == 'sale'),
      );
      if (page >= (object(result['meta'])['last_page'] as int? ?? 1)) {
        return rows;
      }
      page++;
    }
  }

  // Existing /me/orders includes rental orders; count actual sales only.
  Future<Json> overview() async {
    final results = await Future.wait<dynamic>([
      api.get('/me/reservations', {'per_page': 100}),
      saleOrders(),
      api.get('/me/receipts', {'per_page': 3}),
    ]);
    return {
      'reservations_total': object(results[0]['meta'])['total'] ?? 0,
      'orders_total': (results[1] as List<Json>).length,
      'reservations': results[0]['data'],
      'orders': results[1],
      'receipts': results[2]['data'],
    };
  }
}
