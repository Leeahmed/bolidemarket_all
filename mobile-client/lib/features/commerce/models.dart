import 'dart:math';
import 'package:clock/clock.dart';
import 'package:timezone/data/latest.dart' as tzdata;
import 'package:timezone/timezone.dart' as tz;
import '../../core/api.dart';
import '../../core/models.dart';

DateTime commerceNow() => clock.now();

bool _zonesReady = false;
tz.Location shopZone(String name) {
  if (!_zonesReady) {
    tzdata.initializeTimeZones();
    _zonesReady = true;
  }
  return tz.getLocation(
    name,
  ); // Unknown shop zones fail closed; never assume device timezone.
}

DateTime civil(DateTime date) => DateTime.utc(date.year, date.month, date.day);
String dateInput(DateTime date) =>
    '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
DateTime shopToday(String name, {DateTime? now}) {
  final local = tz.TZDateTime.from(now ?? commerceNow(), shopZone(name));
  return civil(local);
}

DateTime shopMidnight(DateTime date, String zone) =>
    tz.TZDateTime(shopZone(zone), date.year, date.month, date.day).toUtc();
String displayDate(dynamic raw, [String? zone]) {
  final value = DateTime.tryParse(text(raw));
  if (value == null) return '—';
  final local = zone == null
      ? value.toLocal()
      : tz.TZDateTime.from(value, shopZone(zone));
  return '${local.day.toString().padLeft(2, '0')}/${local.month.toString().padLeft(2, '0')}/${local.year}';
}

String newIntentKey() {
  final random = Random.secure();
  return 'mobile-${List.generate(24, (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0')).join()}';
}

enum DemoPayment {
  mobileMoney('MOBILE_MONEY_DEMO', 'Mobile Money'),
  card('CARD_DEMO', 'Carte bancaire'),
  cash('CASH_DEMO', 'Espèces'),
  transfer('BANK_TRANSFER_DEMO', 'Virement bancaire');

  const DemoPayment(this.value, this.label);
  final String value, label;
  static String labelFor(dynamic value) =>
      values.where((p) => p.value == value).firstOrNull?.label ?? text(value);
}

String statusLabel(String status) =>
    const {
      'pending': 'En attente',
      'confirmed': 'Confirmée',
      'active': 'Active',
      'completed': 'Terminée',
      'cancelled': 'Annulée',
      'expired': 'Expirée',
      'rejected': 'Refusée',
      'fulfilled': 'Livrée',
      'paid': 'Payé — DEMO',
    }[status] ??
    status;
Money recordMoney(Json json, [String field = 'total_minor']) => Money(
  BigInt.parse(text(json[field])),
  text(json['currency']),
  json['minor_unit'] as int,
);

class CommerceRecord {
  CommerceRecord(this.json);
  final Json json;
  String get id => text(json['id']);
  String get reference => text(json['reference']);
  String get status => text(json['status']);
  Json get vehicle => object(json['vehicle']);
  Json get shop => object(json['shop']);
  String get title => text(vehicle['title']);
  String get receipt => text(json['receipt_reference']);
  Money get total => recordMoney(json);
  bool canCancelAt(DateTime now) =>
      status == 'pending' ||
      (status == 'confirmed' &&
          DateTime.tryParse(text(json['starts_at']))?.isAfter(now) == true);
}

class Receipt {
  Receipt(this.json);
  final Json json;
  String get reference => text(json['reference']);
  Money get total => recordMoney(json);
  Json get vehicle => object(json['vehicle']);
  Json get seller => object(json['seller']);
  Json get buyer => object(json['buyer']);
  Json get transaction => object(json['transaction']);
}

class Availability {
  Availability(this.json);
  final Json json;
  String get timezone => text(json['timezone']);
  DateTime get from => civil(
    tz.TZDateTime.from(DateTime.parse(text(json['from'])), shopZone(timezone)),
  );
  DateTime get to => civil(
    tz.TZDateTime.from(DateTime.parse(text(json['to'])), shopZone(timezone)),
  );
  bool get offered =>
      json['is_for_rent'] == true && json['inventory_status'] == 'available';
  List<Json> get intervals =>
      (json['intervals'] as List? ?? []).map(object).toList();
  bool overlaps(DateTime start, DateTime end, {DateTime? now}) {
    final a = shopMidnight(start, timezone), b = shopMidnight(end, timezone);
    return intervals.any((row) {
      final expiry = DateTime.tryParse(text(row['expires_at']));
      if (expiry != null && !expiry.isAfter(now ?? commerceNow())) {
        return false;
      }
      final x = DateTime.parse(text(row['starts_at']));
      final y = DateTime.tryParse(text(row['ends_at']));
      return x.isBefore(b) && (y == null || y.isAfter(a));
    });
  }

  bool dayFree(DateTime date, {DateTime? now}) =>
      offered &&
      !date.isBefore(shopToday(timezone, now: now)) &&
      !date.isBefore(from) &&
      date.isBefore(to) &&
      !overlaps(date, date.add(const Duration(days: 1)), now: now);
  bool validRange(DateTime start, DateTime end, {DateTime? now}) =>
      offered &&
      !start.isBefore(shopToday(timezone, now: now)) &&
      !start.isBefore(from) &&
      !end.isAfter(to) &&
      end.isAfter(start) &&
      end.difference(start).inDays <= 365 &&
      !overlaps(start, end, now: now);
}

String commerceError(Object error, {required bool rental}) {
  final failure = ApiFailure.from(error);
  if (failure.status != 409) return failure.message;
  return switch (failure.code) {
    'QUOTE_EXPIRED' => 'Le devis a expiré. Choisissez à nouveau vos dates.',
    'PRICE_CHANGED' => 'Le prix a changé. Vérifiez le nouveau résumé.',
    'IDEMPOTENCY_CONFLICT' =>
      'Cette tentative ne correspond plus au résumé. Actualisez le parcours.',
    'VEHICLE_UNAVAILABLE' =>
      rental
          ? 'Ces dates viennent de devenir indisponibles.'
          : 'Ce véhicule n’est plus disponible. Il peut avoir été vendu ou retenu.',
    _ => failure.message,
  };
}

Json reservationRequest(String quoteId, DemoPayment payment) => {
  'quote_id': quoteId,
  'payment_method': payment.value,
};
Json orderRequest(String vehicleId, DemoPayment payment, Json handover) => {
  'vehicle_id': vehicleId,
  'payment_method': payment.value,
  'handover': handover,
};
