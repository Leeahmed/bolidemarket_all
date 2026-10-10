import 'dart:async';
import 'package:bolidemarket/core/api.dart';
import 'package:bolidemarket/core/models.dart';
import 'package:bolidemarket/features/commerce/models.dart';
import 'package:bolidemarket/features/commerce/repository.dart';
import 'package:bolidemarket/features/realtime/reverb.dart';
import 'support.dart';

Vehicle rentalVehicle() => Vehicle({
  ...sample(rental: true).json,
  'shop': {
    'slug': 'demo-shop',
    'name': 'Demo Shop',
    'timezone': 'Africa/Abidjan',
  },
});
Json recordJson({String status = 'pending', bool rental = true}) => {
  'id': '12',
  'reference': rental ? 'BM-RSV-2026-QA' : 'BM-ORD-2026-QA',
  'kind': rental ? 'rental' : 'sale',
  'status': status,
  'vehicle': {
    'id': '1',
    'slug': 'toyota-rav4',
    'title': 'Toyota RAV4',
    'year': 2024,
  },
  'shop': {
    'name': 'Demo Shop',
    'slug': 'demo-shop',
    'timezone': 'Africa/Abidjan',
  },
  'currency': 'XOF',
  'minor_unit': 0,
  'subtotal_minor': '180000',
  'fees_minor': '0',
  'total_minor': '180000',
  'daily_price_minor': '45000',
  'billable_days': 4,
  'shop_timezone': 'Africa/Abidjan',
  'starts_at': commerceNow()
      .add(const Duration(days: 5))
      .toUtc()
      .toIso8601String(),
  'ends_at': commerceNow()
      .add(const Duration(days: 9))
      .toUtc()
      .toIso8601String(),
  'payment_method_demo': 'CARD_DEMO',
  'created_at': commerceNow().toUtc().toIso8601String(),
  'receipt_reference': null,
};
Json receiptJson() => {
  'reference': 'BM-RCP-2026-QA',
  'order_id': '12',
  'type': 'rental',
  'currency': 'EUR',
  'minor_unit': 2,
  'subtotal_minor': '180050',
  'fees_minor': '0',
  'total_minor': '180050',
  'issued_at': '2026-10-09T12:00:00Z',
  'payment_method': 'CARD_DEMO',
  'payment_status': 'paid',
  'buyer': {'name': 'Client historique', 'email': 'qa@example.test'},
  'seller': {
    'name': 'Vendeur historique',
    'slug': 'demo-shop',
    'address': 'Adresse historique',
  },
  'vehicle': {
    'title': 'Peugeot historique',
    'year': 2024,
    'slug': 'vehicle-qa',
  },
  'transaction': {
    'order_reference': 'BM-ORD-QA',
    'payment_reference': 'BM-PAY-QA',
    'reservation_id': '12',
    'days': 4,
    'timezone': 'Africa/Abidjan',
    'starts_at': '2026-10-05T00:00:00Z',
    'ends_at': '2026-10-09T00:00:00Z',
  },
  'is_demo': true,
  'demo_notice': 'Transaction de démonstration.',
};
Availability openAvailability() => Availability({
  'timezone': 'Africa/Abidjan',
  'is_for_rent': true,
  'inventory_status': 'available',
  'from': shopToday('Africa/Abidjan').toIso8601String(),
  'to': shopToday(
    'Africa/Abidjan',
  ).add(const Duration(days: 365)).toIso8601String(),
  'intervals': <Json>[],
});

class FakeCommerce extends CommerceRepository {
  FakeCommerce() : super(ApiClient());
  List<Json> submitted = [];
  List<String> keys = [];
  int cancellations = 0, quotes = 0;
  Json row = recordJson();
  Object? failure;
  Completer<CommerceRecord>? pending;
  @override
  Future<Availability> availability(
    String slug, {
    String? from,
    String? to,
  }) async => openAvailability();
  @override
  Future<Json> quote(String id, DateTime start, DateTime end) async {
    quotes++;
    if (failure != null) throw failure!;
    return {
      'id': 'quote-1',
      'billable_days': end.difference(start).inDays,
      'starts_at': start.toIso8601String(),
      'ends_at': end.toIso8601String(),
      'shop_timezone': 'Africa/Abidjan',
      'daily_price_minor': '45000',
      'total_minor': '${45000 * end.difference(start).inDays}',
      'currency': 'XOF',
      'minor_unit': 0,
    };
  }

  @override
  Future<CommerceRecord> reserve(Json body, String key) async {
    submitted.add(body);
    keys.add(key);
    if (failure != null) throw failure!;
    return pending == null ? CommerceRecord(row) : pending!.future;
  }

  @override
  Future<CommerceRecord> buy(Json body, String key) => reserve(body, key);
  @override
  Future<PageData<CommerceRecord>> list(String kind, {int page = 1}) async =>
      PageData([CommerceRecord(row)], 1, 1, 1);
  @override
  Future<CommerceRecord> detail(String kind, String identifier) async =>
      CommerceRecord(row);
  @override
  Future<CommerceRecord> cancel(String id) async {
    cancellations++;
    row = {...row, 'status': 'cancelled'};
    return CommerceRecord(row);
  }
}

class FakeReceipts extends ReceiptRepository {
  FakeReceipts() : super(ApiClient());
  @override
  Future<PageData<Receipt>> list({int page = 1}) async =>
      PageData([Receipt(receiptJson())], 1, 1, 1);
  @override
  Future<Receipt> detail(String reference) async => Receipt(receiptJson());
  @override
  Future<List<int>> pdf(String reference) async => '%PDF-1.4\nQA'.codeUnits;
}

class FakeRealtime implements RealtimeTransport {
  final controller = StreamController<DomainSignal>.broadcast();
  List<String?> identities = [];
  List<bool> lifecycles = [];
  @override
  Stream<DomainSignal> get signals => controller.stream;
  @override
  void identity(String? userId) {
    identities.add(userId);
  }

  @override
  void foreground(bool active) {
    lifecycles.add(active);
  }

  @override
  void dispose() {
    controller.close();
  }
}
