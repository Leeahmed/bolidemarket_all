import 'dart:convert';
import '../../core/api.dart';
import '../../core/models.dart';
import '../commerce/models.dart';

Json copyDemo(Json row) => object(jsonDecode(jsonEncode(row)));
const demoLocation = {
  'country': {'code': 'CI', 'name': 'Côte d’Ivoire', 'currency_code': 'XOF'},
  'city': {'id': 1, 'name': 'Abidjan'},
  'district': {'id': 1, 'name': 'Cocody'},
};
Json demoMoney(int amount) => {
  'amount_minor': '$amount',
  'currency': 'XOF',
  'minor_unit': 0,
};

class DemoState {
  DemoState() {
    const names = [
      'Abidjan Prestige Motors',
      'Cocody Auto Selection',
      'Lagune Rent Cars',
      'Ivoire Premium Auto',
    ];
    const slugs = [
      'abidjan-prestige-motors',
      'cocody-auto-selection',
      'lagune-rent-cars',
      'ivoire-premium-auto',
    ];
    const photos = ['prestige', 'cocody', 'lagune', 'ivoire'];
    for (var i = 0; i < names.length; i++) {
      shops.add({
        'id': i + 1,
        'slug': slugs[i],
        'name': names[i],
        'location': demoLocation,
        'timezone': 'Africa/Abidjan',
        'is_demo': true,
        'cover_url': 'assets/images/shop-${photos[i]}.webp',
        'logo_url': 'assets/images/symbol.png',
        'description':
            'Professionnel automobile à Abidjan. Boutique et coordonnées de démonstration.',
        'phone': '+2250700000000',
        'email': 'contact@bolidemarket.demo',
        'address': 'Cocody, Abidjan — adresse de démonstration',
      });
    }
    addVehicle(
      1,
      'toyota-rav4',
      'Toyota',
      'RAV4',
      'SUV',
      18500000,
      null,
      0,
      'rav4',
      'Argent',
    );
    addVehicle(
      2,
      'peugeot-208',
      'Peugeot',
      '208',
      'Citadine',
      null,
      45000,
      2,
      '208',
      'Rouge',
    );
    addVehicle(
      3,
      'mercedes-c300',
      'Mercedes-Benz',
      'C300',
      'Luxe',
      27900000,
      null,
      3,
      'c300',
      'Argent',
    );
    // No official Kia photograph exists: use the genuine missing-photo fallback.
    addVehicle(
      4,
      'kia-sportage',
      'Kia',
      'Sportage',
      'SUV',
      16900000,
      null,
      1,
      '',
      'Rouge',
      sold: true,
    );
    addVehicle(
      5,
      'tesla-model-3',
      'Tesla',
      'Model 3',
      'Électrique',
      24500000,
      null,
      0,
      'electric',
      'Blanc',
    );
    addVehicle(
      6,
      'renault-kangoo',
      'Renault',
      'Kangoo',
      'Utilitaire',
      8900000,
      35000,
      2,
      'kangoo',
      'Blanc',
    );
    for (final s in shops) {
      final rows = vehicles.where(
        (v) => object(v['shop'])['slug'] == s['slug'],
      );
      s['sale_vehicles_count'] = rows
          .where((v) => v['sale_price'] != null)
          .length;
      s['rental_vehicles_count'] = rows
          .where((v) => v['rental_daily_price'] != null)
          .length;
      s['vehicles_count'] = rows.length;
    }
    final today = shopToday('Africa/Abidjan');
    final q = makeQuote(
      '2',
      today.add(const Duration(days: 7)),
      today.add(const Duration(days: 10)),
    );
    addTransaction(true, byId('2'), q, DemoPayment.card.value);
    addTransaction(false, byId('1'), {}, DemoPayment.mobileMoney.value);
    final sold = addTransaction(false, byId('4'), {}, DemoPayment.cash.value);
    sold['status'] = 'fulfilled';
  }
  Json user = {
    'id': 'visual-client',
    'email': 'djak@bolidemarket.demo',
    'first_name': 'Djak',
    'last_name': 'Kouadou',
    'phone': '+2250700000000',
    'country_code': 'CI',
    'country': demoLocation['country'],
    'city_id': 1,
    'city': demoLocation['city'],
    'district_id': 1,
    'district': demoLocation['district'],
    'avatar_url': 'assets/images/symbol.png',
    'role': 'client',
    'is_demo': true,
  };
  bool connected = true;
  int sequence = 100;
  final shops = <Json>[],
      vehicles = <Json>[],
      reservations = <Json>[],
      orders = <Json>[],
      receipts = <Json>[];
  final favorites = <String>{'1', '2'};
  final quotes = <String, Json>{};
  final intents = <String, (String, Json)>{};
  void requireClient() {
    if (!connected) {
      throw const ApiFailure('Connectez-vous pour continuer.', status: 401);
    }
  }

  void addVehicle(
    int id,
    String slug,
    String brand,
    String model,
    String category,
    int? sale,
    int? rent,
    int shop,
    String photo,
    String color, {
    bool sold = false,
  }) {
    final url = photo.isEmpty ? '' : 'assets/images/vehicle-$photo.webp';
    vehicles.add({
      'id': '$id',
      'slug': slug,
      'brand': {'id': id, 'name': brand},
      'model': {'id': id, 'brand_id': id, 'name': model},
      'category': {
        'id': category == 'SUV' ? 1 : id,
        'name': category,
        'slug': {
          'SUV': 'suv',
          'Citadine': 'city-car',
          'Luxe': 'luxury',
          'Électrique': 'electric',
          'Utilitaire': 'utility',
        }[category],
      },
      'mileage_km': id * 4200,
      'fuel': brand == 'Tesla' ? 'electric' : 'petrol',
      'year': 2024,
      'mileage': id * 4200,
      'color': color,
      'fuel_type': brand == 'Tesla' ? 'electric' : 'petrol',
      'transmission': 'automatic',
      'seats': 5,
      'doors': 5,
      'inventory_status': sold ? 'sold' : 'available',
      'publication_status': 'published',
      'is_demo': true,
      'is_favorite': favorites.contains('$id'),
      'sale_price': sale == null ? null : demoMoney(sale),
      'rental_daily_price': rent == null ? null : demoMoney(rent),
      'offer_types': [if (sale != null) 'sale', if (rent != null) 'rent'],
      'location': demoLocation,
      'country_code': 'CI',
      'city_id': 1,
      'district_id': 1,
      'shop': shops[shop],
      'primary_image': {'id': id, 'url': url},
      'images': url.isEmpty
          ? <Json>[]
          : [
              {'id': id, 'url': url},
            ],
      'description':
          'Véhicule de démonstration : présentation soignée, entretien suivi. Aucune annonce réelle.',
      'features': [
        {'label': 'Climatisation'},
        {'label': 'Caméra de recul'},
        {'label': 'Bluetooth'},
      ],
      'distance_km': double.parse((id * 1.2).toStringAsFixed(1)),
      'created_at': commerceNow()
          .subtract(Duration(days: id))
          .toIso8601String(),
    });
  }

  Json byId(String id) => vehicles.firstWhere(
    (v) => text(v['id']) == id,
    orElse: () => throw const ApiFailure('Véhicule introuvable.', status: 404),
  );
  Json bySlug(String slug) => vehicles.firstWhere(
    (v) => v['slug'] == slug,
    orElse: () => throw const ApiFailure('Annonce introuvable.', status: 404),
  );
  Json vehicleSnapshot(Json v) => {
    'id': v['id'],
    'slug': v['slug'],
    'title': Vehicle(v).title,
    'year': v['year'],
  };
  Json makeQuote(String id, DateTime start, DateTime end) {
    final v = byId(id);
    final days = end.difference(start).inDays;
    if (days < 1 || days > 365 || v['rental_daily_price'] == null) {
      throw const ApiFailure('Période de location invalide.', status: 422);
    }
    final daily = Money.fromJson(object(v['rental_daily_price'])).amount;
    final q = {
      'id': 'visual-quote-${++sequence}',
      'vehicle_id': id,
      'billable_days': days,
      'starts_at': start.toUtc().toIso8601String(),
      'ends_at': end.toUtc().toIso8601String(),
      'shop_timezone': 'Africa/Abidjan',
      'daily_price_minor': '$daily',
      'total_minor': '${daily * BigInt.from(days)}',
      'currency': 'XOF',
      'minor_unit': 0,
      'expires_at': commerceNow()
          .add(const Duration(minutes: 5))
          .toUtc()
          .toIso8601String(),
    };
    quotes[text(q['id'])] = q;
    return copyDemo(q);
  }

  Json addTransaction(
    bool rental,
    Json v,
    Json q,
    String payment, {
    Json handover = const {},
  }) {
    final id = '${++sequence}', year = commerceNow().year;
    final total = rental
        ? q['total_minor']
        : object(v['sale_price'])['amount_minor'];
    final row = {
      'id': id,
      'reference': 'BM-${rental ? 'RSV' : 'ORD'}-$year-VISUAL-$id',
      'kind': rental ? 'rental' : 'sale',
      'status': 'confirmed',
      'vehicle': vehicleSnapshot(v),
      'shop': copyDemo(object(v['shop'])),
      'currency': 'XOF',
      'minor_unit': 0,
      'subtotal_minor': total,
      'total_minor': total,
      'fees_minor': '0',
      'is_demo': true,
      'payment_method_demo': payment,
      'payment': {
        'status': 'paid',
        'method_demo': payment,
        'reference': 'BM-PAY-VISUAL-$id',
      },
      'created_at': commerceNow().toUtc().toIso8601String(),
      'shop_timezone': 'Africa/Abidjan',
      if (rental) ...q,
      if (!rental)
        'handover': {
          'mode': 'self',
          'contact_name': AppUser(user).name,
          'contact_phone': user['phone'],
          'scheduled_local':
              '${dateInput(shopToday('Africa/Abidjan').add(const Duration(days: 1)))}T12:00',
          ...copyDemo(handover),
          'timezone': 'Africa/Abidjan',
          'scheduled_at':
              '${handover['scheduled_local'] ?? '${dateInput(shopToday('Africa/Abidjan').add(const Duration(days: 1)))}T12:00'}:00Z',
        },
      'receipt_reference': 'BM-RCP-$year-VISUAL-$id',
    };
    row['id'] = id;
    if (rental) {
      reservations.insert(0, row);
      // Receipt's order link opens the corresponding local rental order.
      row['order'] = {
        'id': id,
        'reference': 'BM-ORD-$year-VISUAL-$id',
        'payment': copyDemo(object(row['payment'])),
      };
      orders.insert(0, {
        ...copyDemo(row),
        'reference': 'BM-ORD-$year-VISUAL-$id',
      });
    } else {
      orders.insert(0, row);
    }
    receipts.insert(0, {
      'reference': row['receipt_reference'],
      'order_id': id,
      'type': rental ? 'rental' : 'sale',
      'currency': 'XOF',
      'minor_unit': 0,
      'subtotal_minor': total,
      'total_minor': total,
      'fees_minor': '0',
      'issued_at': row['created_at'],
      'payment_method': payment,
      'payment_status': 'paid',
      'buyer': {
        'name': AppUser(user).name,
        'email': user['email'],
        'phone': user['phone'],
        'country': 'Côte d’Ivoire',
        'city': 'Abidjan',
      },
      'seller': copyDemo(object(v['shop'])),
      'vehicle': vehicleSnapshot(v),
      'transaction': {
        'order_reference': 'BM-ORD-$year-VISUAL-$id',
        'payment_reference': 'BM-PAY-VISUAL-$id',
        'timezone': 'Africa/Abidjan',
        if (rental) ...{
          'reservation_id': id,
          'days': q['billable_days'],
          'starts_at': q['starts_at'],
          'ends_at': q['ends_at'],
        },
      },
      'is_demo': true,
      'demo_notice':
          'Aperçu local : confirmation et paiement simulés. Aucun paiement ni document fiscal réel.',
    });
    return row;
  }
}

PageData<T> demoPage<T>(List<T> rows, int page, {int size = 12}) {
  final last = (rows.length / size).ceil().clamp(1, 1000000);
  return PageData(
    rows.skip((page - 1) * size).take(size).toList(),
    page,
    last,
    rows.length,
  );
}
