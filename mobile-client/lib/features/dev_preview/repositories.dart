import 'dart:convert';
import 'package:dio/dio.dart';
import '../../core/api.dart';
import '../../core/models.dart';
import '../auth/auth_repository.dart';
import '../marketplace/repositories.dart';
import '../marketplace/query.dart';
import '../location/location_repository.dart';
import '../commerce/repository.dart';
import '../commerce/models.dart';
import '../realtime/reverb.dart';
import 'fixtures.dart';

/// Fail closed even if a newly added real repository is accidentally used in QA.
class DemoApiClient extends ApiClient {
  DemoApiClient() {
    dio.interceptors.insert(
      0,
      InterceptorsWrapper(
        onRequest: (options, handler) {
          handler.reject(
            DioException(
              requestOptions: options,
              error: const ApiFailure(
                'API réseau interdite dans l’aperçu local.',
              ),
            ),
          );
        },
      ),
    );
  }
  @override
  Future<Json> get(String path, [Json query = const {}]) => Future.error(
    const ApiFailure('API réseau interdite dans l’aperçu local.'),
  );
}

class DemoSessionStore implements SessionStore {
  String? token = 'visual-local-session';
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

class DemoAuthRepository extends AuthRepository {
  DemoAuthRepository(super.api, super.store, this.data);
  final DemoState data;
  @override
  Future<AppUser> me() async {
    data.requireClient();
    return AppUser(copyDemo(data.user));
  }

  @override
  Future<AppUser> login(String email, String password) async {
    data.connected = true;
    await store.writeToken('visual-local-session');
    api.token = 'visual-local-session';
    return me();
  }

  @override
  Future<void> register(Json fields) async {
    // Local form rehearsal only; no real account and no persistent credentials.
  }
  @override
  Future<void> forgot(String email) async {}
  @override
  Future<void> logout() async {
    data.connected = false;
    api.token = null;
    await store.deleteToken();
  }
}

class DemoVehicleRepository extends VehicleRepository {
  DemoVehicleRepository(super.api, this.data);
  final DemoState data;
  Vehicle decode(Json v) => Vehicle({
    ...copyDemo(v),
    'is_favorite': data.connected && data.favorites.contains(text(v['id'])),
  });
  @override
  Future<Vehicle> detail(String slug) async => decode(data.bySlug(slug));
  @override
  Future<PageData<Vehicle>> search(
    SearchQuery query, {
    int page = 1,
    String? shop,
  }) async {
    final p = query.parameters(page: page);
    var rows = data.vehicles.where((v) {
      final vehicle = Vehicle(v);
      if (shop != null && vehicle.shopSlug != shop) return false;
      final needle = text(p['q']).toLowerCase();
      if (needle.isNotEmpty &&
          !('${vehicle.title} ${vehicle.shopName} ${vehicle.location} ${text(v['color'])}')
              .toLowerCase()
              .contains(needle)) {
        return false;
      }
      for (final field in ['brand', 'model', 'category']) {
        final expected = p[field];
        final actual = object(v[field]);
        if (expected != null &&
            text(expected) !=
                text(actual[field == 'category' ? 'slug' : 'name'])) {
          return false;
        }
      }
      final offer = text(p['listing_type']);
      if (offer == 'sale' && !vehicle.sale ||
          offer == 'rental' && !vehicle.rental) {
        return false;
      }
      if (p['status'] != null && p['status'] != v['inventory_status']) {
        return false;
      }
      for (final k in ['brand', 'model', 'category']) {
        if (p['${k}_id'] != null &&
            text(p['${k}_id']) != text(object(v[k])['id'])) {
          return false;
        }
      }
      for (final k in [
        'country_code',
        'city_id',
        'district_id',
        'fuel_type',
        'transmission',
      ]) {
        if (p[k] != null && text(p[k]) != text(v[k])) return false;
      }
      if (p['currency'] != null && p['currency'] != 'XOF') return false;
      final price = vehicle.price(offer);
      for (final k in [
        'year_min',
        'year_max',
        'mileage_min',
        'mileage_max',
        'min_price',
        'max_price',
      ]) {
        if (p[k] == null) continue;
        final limit = BigInt.tryParse(text(p[k]));
        final value = k.contains('price')
            ? price?.amount
            : BigInt.from(
                (v[k.startsWith('year') ? 'year' : 'mileage'] as num).toInt(),
              );
        if (limit == null || value == null) return false;
        if ((k.endsWith('min') || k.startsWith('min_')) && value < limit ||
            (k.endsWith('max') || k.startsWith('max_')) && value > limit) {
          return false;
        }
      }
      return true;
    }).toList();
    final sort = text(p['sort']);
    if (sort.startsWith('price_')) {
      rows.sort(
        (a, b) =>
            Vehicle(a)
                .price(text(p['listing_type']))!
                .amount
                .compareTo(Vehicle(b).price(text(p['listing_type']))!.amount) *
            (sort == 'price_desc' ? -1 : 1),
      );
    } else if (sort == 'year_desc' ||
        sort == 'mileage_asc' ||
        sort == 'distance') {
      final field = sort == 'year_desc'
          ? 'year'
          : sort == 'distance'
          ? 'distance_km'
          : 'mileage';
      rows.sort(
        (a, b) =>
            (a[field] as num).compareTo(b[field] as num) *
            (sort == 'year_desc' ? -1 : 1),
      );
    } else {
      rows.sort(
        (a, b) => text(b['created_at']).compareTo(text(a['created_at'])),
      );
    }
    return demoPage(rows.map(decode).toList(), page);
  }

  @override
  Future<Json> config() async => {
    'default_country': 'CI',
    'default_currency': 'XOF',
    'is_demo': true,
  };
  @override
  Future<List<Json>> references(String kind, [Json params = const {}]) async {
    final refs = <String, List<Json>>{
      'countries': [
        {
          'code': 'CI',
          'name': 'Côte d’Ivoire',
          'phone_code': '+225',
          'currency_code': 'XOF',
        },
      ],
      'currencies': [
        {'code': 'XOF', 'name': 'Franc CFA', 'minor_unit': 0},
      ],
      'cities': [
        {'id': 1, 'name': 'Abidjan', 'country_code': 'CI'},
      ],
      'districts': [
        {'id': 1, 'name': 'Cocody', 'city_id': 1},
      ],
      for (final pair in [
        ('brands', 'brand'),
        ('models', 'model'),
        ('categories', 'category'),
      ])
        pair.$1: {
          for (final v in data.vehicles)
            text(object(v[pair.$2])['id']): object(v[pair.$2]),
        }.values.toList(),
    };
    return (refs[kind] ?? [])
        .where(
          (r) => params.entries.every(
            (e) => !r.containsKey(e.key) || text(r[e.key]) == text(e.value),
          ),
        )
        .map(copyDemo)
        .toList();
  }
}

class DemoShopRepository extends ShopRepository {
  DemoShopRepository(super.api, this.data);
  final DemoState data;
  @override
  Future<List<Json>> list() async => data.shops.map(copyDemo).toList();
  @override
  Future<Json> detail(String slug) async => copyDemo(
    data.shops.firstWhere(
      (s) => s['slug'] == slug,
      orElse: () =>
          throw const ApiFailure('Boutique introuvable.', status: 404),
    ),
  );
}

class DemoFavoriteRepository extends FavoriteRepository {
  DemoFavoriteRepository(super.api, this.data);
  final DemoState data;
  @override
  Future<PageData<Vehicle>> list({int page = 1}) async {
    data.requireClient();
    return demoPage(
      data.vehicles
          .where((v) => data.favorites.contains(text(v['id'])))
          .map((v) => Vehicle({...copyDemo(v), 'is_favorite': true}))
          .toList(),
      page,
    );
  }

  @override
  Future<void> set(String id, bool favorite) async {
    data.requireClient();
    data.byId(id);
    favorite ? data.favorites.add(id) : data.favorites.remove(id);
  }
}

class DemoAccountRepository extends AccountRepository {
  DemoAccountRepository(super.api, this.data);
  final DemoState data;
  @override
  Future<AppUser> update(Json fields) async {
    data.requireClient();
    data.user = {...data.user, ...fields};
    return AppUser(copyDemo(data.user));
  }

  @override
  Future<AppUser> avatar(List<int> bytes, String name) => update({
    'avatar_url':
        'data:image/${name.toLowerCase().endsWith('.png') ? 'png' : 'jpeg'};base64,${base64Encode(bytes)}',
  });
  @override
  Future<List<Json>> saleOrders() async {
    data.requireClient();
    return data.orders.where((r) => r['kind'] == 'sale').map(copyDemo).toList();
  }

  @override
  Future<Json> overview() async {
    data.requireClient();
    return {
      'reservations_total': data.reservations.length,
      'orders_total': (await saleOrders()).length,
      'reservations': data.reservations.map(copyDemo).toList(),
      'orders': await saleOrders(),
      'receipts': data.receipts.take(3).map(copyDemo).toList(),
    };
  }
}

class DemoLocationRepository extends LocationRepository {
  @override
  Future<Json> locate() async => {'latitude': 5.3599, 'longitude': -3.9946};
}

class DemoCommerceRepository extends CommerceRepository {
  DemoCommerceRepository(super.api, this.data);
  final DemoState data;
  @override
  Future<Json> post(String path, Json body, {String? key}) => Future.error(
    const ApiFailure('Utilisez le repository local de démonstration.'),
  );
  @override
  Future<Availability> availability(
    String slug, {
    String? from,
    String? to,
  }) async {
    final v = data.bySlug(slug);
    return Availability({
      'timezone': 'Africa/Abidjan',
      'is_for_rent': Vehicle(v).rental,
      'inventory_status': v['inventory_status'],
      'from': DateTime.parse(
        from ?? dateInput(shopToday('Africa/Abidjan')),
      ).toUtc().toIso8601String(),
      'to': DateTime.parse(
        to ??
            dateInput(
              shopToday('Africa/Abidjan').add(const Duration(days: 365)),
            ),
      ).toUtc().toIso8601String(),
      'intervals': data.reservations
          .where(
            (r) =>
                object(r['vehicle'])['slug'] == slug &&
                ['confirmed', 'pending', 'active'].contains(r['status']),
          )
          .map((r) => {'starts_at': r['starts_at'], 'ends_at': r['ends_at']})
          .toList(),
    });
  }

  @override
  Future<Json> quote(String id, DateTime start, DateTime end) async {
    data.requireClient();
    final a = await availability(text(data.byId(id)['slug']));
    if (!a.validRange(start, end)) {
      throw const ApiFailure(
        'Dates indisponibles.',
        status: 409,
        code: 'VEHICLE_UNAVAILABLE',
      );
    }
    return data.makeQuote(id, start, end);
  }

  Future<CommerceRecord> create(bool rental, Json body, String key) async {
    data.requireClient();
    final payload = jsonEncode(body);
    if (data.intents.containsKey(key)) {
      final previous = data.intents[key]!;
      if (previous.$1 != payload) {
        throw const ApiFailure(
          'Tentative modifiée.',
          status: 409,
          code: 'IDEMPOTENCY_CONFLICT',
        );
      }
      return CommerceRecord(copyDemo(previous.$2));
    }
    final payment = text(body['payment_method']);
    if (!DemoPayment.values.any((p) => p.value == payment)) {
      throw const ApiFailure('Paiement DEMO requis.', status: 422);
    }
    final q = rental
        ? data.quotes[text(body['quote_id'])]
        : <String, dynamic>{};
    if (q == null) {
      throw const ApiFailure(
        'Devis expiré.',
        status: 409,
        code: 'QUOTE_EXPIRED',
      );
    }
    final v = data.byId(text(rental ? q['vehicle_id'] : body['vehicle_id']));
    if (v['inventory_status'] != 'available') {
      throw const ApiFailure(
        'Véhicule indisponible.',
        status: 409,
        code: 'VEHICLE_UNAVAILABLE',
      );
    }
    if (rental) {
      if (!DateTime.parse(text(q['expires_at'])).isAfter(commerceNow())) {
        throw const ApiFailure(
          'Devis expiré.',
          status: 409,
          code: 'QUOTE_EXPIRED',
        );
      }
      final a = await availability(text(v['slug']));
      if (!a.validRange(
        DateTime.parse(text(q['starts_at'])),
        DateTime.parse(text(q['ends_at'])),
      )) {
        throw const ApiFailure(
          'Dates indisponibles.',
          status: 409,
          code: 'VEHICLE_UNAVAILABLE',
        );
      }
    } else {
      if (v['sale_price'] == null || object(body['handover']).isEmpty) {
        throw const ApiFailure('Vente et remise requises.', status: 422);
      }
      if (body['expected_price_minor'] != null &&
          text(body['expected_price_minor']) !=
              text(object(v['sale_price'])['amount_minor'])) {
        throw const ApiFailure(
          'Prix modifié.',
          status: 409,
          code: 'PRICE_CHANGED',
        );
      }
    }
    final row = data.addTransaction(
      rental,
      v,
      q,
      payment,
      handover: object(body['handover']),
    );
    data.intents[key] = (payload, copyDemo(row));
    return CommerceRecord(copyDemo(row));
  }

  @override
  Future<CommerceRecord> reserve(Json body, String key) =>
      create(true, body, key);
  @override
  Future<CommerceRecord> buy(Json body, String key) => create(false, body, key);
  @override
  Future<PageData<CommerceRecord>> list(String kind, {int page = 1}) async {
    data.requireClient();
    return demoPage(
      (kind == 'reservations' ? data.reservations : data.orders)
          .map((r) => CommerceRecord(copyDemo(r)))
          .toList(),
      page,
      size: 20,
    );
  }

  @override
  Future<CommerceRecord> detail(String kind, String identifier) async {
    data.requireClient();
    final rows = kind == 'reservations' ? data.reservations : data.orders;
    return CommerceRecord(
      copyDemo(
        rows.firstWhere(
          (r) => text(r['id']) == identifier || r['reference'] == identifier,
          orElse: () =>
              throw const ApiFailure('Transaction introuvable.', status: 404),
        ),
      ),
    );
  }

  @override
  Future<CommerceRecord> cancel(String id) async {
    final record = await detail('reservations', id);
    if (!record.canCancelAt(commerceNow())) {
      throw const ApiFailure('Annulation indisponible.', status: 409);
    }
    final row = data.reservations.firstWhere((r) => text(r['id']) == id);
    row['status'] = 'cancelled';
    for (final order in data.orders.where((r) => text(r['id']) == id)) {
      order['status'] = 'cancelled';
    }
    return CommerceRecord(copyDemo(row));
  }
}

class DemoReceiptRepository extends ReceiptRepository {
  DemoReceiptRepository(super.api, this.data);
  final DemoState data;
  @override
  Future<PageData<Receipt>> list({int page = 1}) async {
    data.requireClient();
    return demoPage(
      data.receipts.map((r) => Receipt(copyDemo(r))).toList(),
      page,
      size: 20,
    );
  }

  @override
  Future<Receipt> detail(String reference) async {
    data.requireClient();
    return Receipt(
      copyDemo(
        data.receipts.firstWhere(
          (r) => r['reference'] == reference,
          orElse: () =>
              throw const ApiFailure('Reçu introuvable.', status: 404),
        ),
      ),
    );
  }

  @override
  Future<List<int>> pdf(String reference) async {
    final r = await detail(reference);
    // Minimal local QA document, explicitly simulated. Native PDFs remain Laravel's.
    String safe(String s) => s
        .replaceAll(RegExp(r'[^ -~]'), ' ')
        .replaceAll('\\', '\\\\')
        .replaceAll('(', '\\(')
        .replaceAll(')', '\\)');
    final lines = [
      'BOLIDEMARKET - RECU DE DEMONSTRATION',
      reference,
      'Client: ${text(r.buyer['name'])}',
      'Vehicule: ${text(r.vehicle['title'])}',
      'Total: ${r.total.format()}',
      'XOF - Aucun paiement reel - Apercu local',
    ];
    final content =
        'BT /F1 14 Tf 40 790 Td ${lines.map((l) => '(${safe(l)}) Tj 0 -28 Td').join(' ')} ET';
    final objects = [
      '<< /Type /Catalog /Pages 2 0 R >>',
      '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
      '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
      '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
      '<< /Length ${content.length} >>\nstream\n$content\nendstream',
    ];
    var out = '%PDF-1.4\n';
    final offsets = <int>[0];
    for (var i = 0; i < objects.length; i++) {
      offsets.add(out.length);
      out += '${i + 1} 0 obj\n${objects[i]}\nendobj\n';
    }
    final xref = out.length;
    out +=
        'xref\n0 6\n0000000000 65535 f \n${offsets.skip(1).map((o) => '${o.toString().padLeft(10, '0')} 00000 n \n').join()}trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF';
    return ascii.encode(out);
  }
}

class DemoRealtimeTransport implements RealtimeTransport {
  @override
  Stream<DomainSignal> get signals => const Stream.empty();
  @override
  void identity(String? userId) {}
  @override
  void foreground(bool active) {}
  @override
  void dispose() {}
}
