// Explicit local integration check; separate from the mocked test suite.
// Uses a dedicated demonstration identity; never prints a token or credentials.
import 'dart:convert';
import 'dart:io';
import 'package:bolidemarket/core/api.dart';
import 'package:bolidemarket/core/models.dart';
import 'package:bolidemarket/features/marketplace/query.dart';
import 'package:bolidemarket/features/marketplace/repositories.dart';

Future<void> main() async {
  final api = ApiClient();
  final vehicles = VehicleRepository(api);
  final shops = ShopRepository(api);
  final favorites = FavoriteRepository(api);
  final account = AccountRepository(api);
  final checks = <String, bool>{};
  final config = object((await api.get('/app-config'))['data']);
  if (config['demo_mode'] != true || AppConfig.environment != 'development') {
    throw StateError('Contrôle local DEMO uniquement.');
  }
  final suffix = DateTime.now().millisecondsSinceEpoch;
  final email = 'qa-mobile8a-$suffix@bolidemarket.demo';
  final password = 'Mobile8ADemo!$suffix';
  await api.dio.post<dynamic>(
    '/auth/register',
    data: {
      'first_name': 'Mobile',
      'last_name': 'Demo',
      'country_code': 'CI',
      'phone': '+2250701234567',
      'email': email,
      'password': password,
      'password_confirmation': password,
    },
  );
  final login = object(
    object(
      (await api.dio.post<dynamic>(
        '/auth/login',
        data: {
          'email': email,
          'password': password,
          'device_name': 'mobile-8a-contract-check',
        },
      )).data,
    )['data'],
  );
  if (text(login['token']).isEmpty) throw StateError('Bearer manquant.');
  api.token = text(login['token']);
  try {
    final me = AppUser(object((await api.get('/auth/me'))['data']));
    checks['register_login_bearer_me'] =
        me.json['demo_mode'] == true &&
        me.json['email_verification_required'] == false;
    checks['countries'] = (await vehicles.references('countries')).isNotEmpty;
    final result = await vehicles.search(
      const SearchQuery(
        values: {
          'country_code': 'CI',
          'location_mode': 'rank',
          'status': 'available',
        },
      ),
    );
    if (result.items.isEmpty) throw StateError('Catalogue vide.');
    checks['home_catalogue'] = result.total > 0;
    final vehicle = await vehicles.detail(result.items.first.slug);
    checks['vehicle_detail'] = vehicle.id == result.items.first.id;
    final shop = await shops.detail(vehicle.shopSlug);
    checks['shop_detail_catalogue'] =
        shop['slug'] == vehicle.shopSlug &&
        (await vehicles.search(
          const SearchQuery(),
          shop: vehicle.shopSlug,
        )).items.every((v) => v.shopSlug == vehicle.shopSlug);
    checks['search_filters_sort'] = (await vehicles.search(
      const SearchQuery(
        values: {
          'q': 'Toyota',
          'listing_type': 'sale',
          'currency': 'XOF',
          'sort': 'price_asc',
        },
      ),
    )).items.every((v) => v.sale && v.price('sale')!.currency == 'XOF');
    final nearby = await vehicles.search(
      const SearchQuery(
        values: {'latitude': 5.3599, 'longitude': -4.0083, 'sort': 'distance'},
      ),
    );
    checks['geographic_query'] = nearby.items.any(
      (v) => v.json['distance_km'] != null,
    );
    await favorites.set(vehicle.id, true);
    checks['favorite_add_list'] = (await favorites.all()).any(
      (v) => v.id == vehicle.id,
    );
    await favorites.set(vehicle.id, false);
    checks['favorite_remove'] = (await favorites.all()).isEmpty;
    final updated = await account.update({
      'first_name': 'Mobile',
      'last_name': 'Demo',
      'country_code': 'CI',
      'phone': '+2250701234567',
      'city_id': me.json['city_id'],
      'district_id': me.json['district_id'],
    });
    checks['profile_update_e164'] = updated.json['phone'] == '+2250701234567';
    final bytes = await File('assets/images/app-icon.png').readAsBytes();
    final avatar = await account.avatar(bytes, 'avatar.png');
    checks['avatar_upload'] = text(avatar.json['avatar_url']).isNotEmpty;
    final counts = await account.overview();
    checks['account_overview'] =
        counts['orders_total'] == 0 && counts['reservations_total'] == 0;
  } finally {
    await api.dio.post<dynamic>('/auth/logout');
  }
  try {
    await api.get('/auth/me');
    checks['logout_revoked'] = false;
  } catch (e) {
    checks['logout_revoked'] = ApiFailure.from(e).status == 401;
  }
  final output = {
    'checks': checks,
    'passed': checks.values.where((v) => v).length,
    'failed': checks.values.where((v) => !v).length,
    'synthetic_account_created': true,
    'transactions_created': 0,
  };
  await Directory('qa').create(recursive: true);
  await File(
    'qa/api-smoke.json',
  ).writeAsString(const JsonEncoder.withIndent('  ').convert(output));
  stdout.writeln(jsonEncode(output));
  if (checks.values.any((v) => !v)) exitCode = 1;
}
