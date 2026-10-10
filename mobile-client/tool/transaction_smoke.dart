// Local DEMO integration only. Credentials/tokens are never logged.
import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:bolidemarket/core/api.dart';
import 'package:bolidemarket/core/models.dart';
import 'package:bolidemarket/features/commerce/models.dart';
import 'package:bolidemarket/features/commerce/repository.dart';
import 'package:bolidemarket/features/marketplace/repositories.dart';
import 'package:bolidemarket/features/realtime/reverb.dart';

Future<Json> post(ApiClient api, String path, Json body) async => object(
  object((await api.dio.post<dynamic>(path, data: body)).data)['data'],
);
Future<Json> login(ApiClient api, String email, String password) async {
  final row = await post(api, '/auth/tokens', {
    'email': email,
    'password': password,
    'device_name': 'mobile8b-demo-check',
  });
  api.token = text(row['token']);
  if (api.token!.isEmpty) throw StateError('Bearer absent');
  return object(row['user']);
}

Future<void> main() async {
  if (AppConfig.environment != 'development' ||
      Uri.parse(AppConfig.baseUrl).host != '127.0.0.1') {
    throw StateError('Local DEMO uniquement');
  }
  final api = ApiClient(),
      rentalPro = ApiClient(),
      salePro = ApiClient(),
      outsider = ApiClient();
  final commerce = CommerceRepository(api),
      receipts = ReceiptRepository(api),
      vehicles = VehicleRepository(api);
  final checks = <String, bool>{}, evidence = <String, dynamic>{};
  final fixture = object(
    jsonDecode(await File('../.tools/mobile8b-fixture.json').readAsString()),
  );
  final rental = object(fixture['rental']), sale = object(fixture['sale']);
  ReverbTransport? transport;
  StreamSubscription<DomainSignal>? listener;
  Future<DomainSignal> wait(String type, [String? id]) => transport!.signals
      .firstWhere(
        (s) =>
            s.type == type &&
            (id == null ||
                text(s.data['id']) == id ||
                text(s.data['vehicle_id']) == id),
      )
      .timeout(const Duration(seconds: 15));
  void check(String name, bool value) {
    checks[name] = value;
    if (!value) throw StateError('Échec: $name');
    stdout.writeln('PASS $name');
  }

  try {
    check(
      'demo_only',
      object((await api.get('/app-config'))['data'])['demo_mode'] == true,
    );
    final suffix = DateTime.now().microsecondsSinceEpoch;
    final email = 'qa-mobile8b-$suffix@bolidemarket.demo',
        password = 'Mobile8BDemo!$suffix';
    await post(api, '/auth/register', {
      'first_name': 'Mobile',
      'last_name': 'DEMO QA',
      'country_code': 'CI',
      'phone': '+2250701234567',
      'email': email,
      'password': password,
      'password_confirmation': password,
    });
    final user = await login(api, email, password);
    await login(rentalPro, text(rental['merchant_email']), 'password');
    await login(salePro, text(sale['merchant_email']), 'password');
    transport = ReverbTransport(
      api,
      ReverbConfig(key: text(fixture['reverb_key']), host: '127.0.0.1'),
    );
    final observed = <String>{};
    listener = transport.signals.listen((s) => observed.add(s.type));
    final subscribed = wait('Reconnected');
    transport.identity(text(user['id']));
    await subscribed;
    // Ensure authenticated subscription is accepted before private transitions.
    if (!observed.contains('Reconnected')) throw StateError('Reverb absent');
    await Future<void>.delayed(const Duration(milliseconds: 400));
    check('reverb_public_private_connected', transport.socket != null);
    final now = shopToday('Africa/Abidjan'),
        start = now.add(const Duration(days: 5)),
        end = now.add(const Duration(days: 9));
    final availability = await commerce.availability(
      text(rental['slug']),
      from: dateInput(now),
      to: dateInput(now.add(const Duration(days: 365))),
    );
    check('availability_window', availability.validRange(start, end));
    final quote = await commerce.quote(text(rental['id']), start, end);
    check('server_quote_4_days', quote['billable_days'] == 4);
    final createdSignal = wait('ReservationCreated');
    final key = newIntentKey(),
        body = reservationRequest(text(quote['id']), DemoPayment.mobileMoney);
    final reservation = await commerce.reserve(body, key);
    await createdSignal;
    check(
      'reservation_pending',
      reservation.status == 'pending' && reservation.receipt.isEmpty,
    );
    check(
      'idempotent_replay',
      (await commerce.reserve(body, key)).id == reservation.id,
    );
    final proList = object(
      await rentalPro.get('/merchant/reservations', {'per_page': 100}),
    );
    check(
      'professional_sees_mobile_reservation',
      (proList['data'] as List).any((r) => object(r)['id'] == reservation.id),
    );
    final confirmedSignal = wait('ReservationConfirmed', reservation.id);
    await post(
      rentalPro,
      '/merchant/reservations/${reservation.id}/confirm',
      {},
    );
    await confirmedSignal;
    final confirmed = await commerce.detail('reservations', reservation.id);
    check(
      'reservation_realtime_confirmed',
      confirmed.status == 'confirmed' && confirmed.receipt.isNotEmpty,
    );
    final receipt = await receipts.detail(confirmed.receipt),
        snapshot = jsonEncode((await receipts.detail(confirmed.receipt)).json);
    check(
      'demo_receipt_server_snapshot',
      receipt.json['is_demo'] == true &&
          receipt.total.amount == confirmed.total.amount,
    );
    final pdf = await receipts.pdf(receipt.reference);
    await Directory('qa').create(recursive: true);
    await File('qa/mobile8b-receipt.pdf').writeAsBytes(pdf);
    check(
      'authenticated_laravel_pdf',
      String.fromCharCodes(pdf.take(5)) == '%PDF-' && pdf.length > 1000,
    );
    var denied = false;
    try {
      await ReceiptRepository(outsider).pdf(receipt.reference);
    } catch (e) {
      denied = ApiFailure.from(e).status == 401;
    }
    check('anonymous_pdf_denied', denied);
    final cancelSignal = wait('ReservationCancelled', reservation.id);
    await commerce.cancel(reservation.id);
    await cancelSignal;
    check(
      'cancelled_and_dates_released',
      (await commerce.detail('reservations', reservation.id)).status ==
              'cancelled' &&
          (await commerce.availability(
            text(rental['slug']),
            from: dateInput(now),
            to: dateInput(end.add(const Duration(days: 1))),
          )).validRange(start, end),
    );
    check(
      'receipt_immutable_after_cancel',
      jsonEncode((await receipts.detail(receipt.reference)).json) == snapshot,
    );
    final current = await vehicles.detail(text(sale['slug']));
    final orderBody = orderRequest(text(sale['id']), DemoPayment.card, {
      'mode': 'self',
      'scheduled_local': '${dateInput(now.add(const Duration(days: 2)))}T12:00',
      'contact_name': 'Client QA DEMO',
      'contact_phone': '+2250701234567',
    })..['expected_price_minor'] = current.price('sale')!.amount.toString();
    final orderSignal = wait('OrderCreated');
    final order = await commerce.buy(orderBody, newIntentKey());
    await orderSignal;
    check(
      'purchase_pending_handover',
      order.status == 'pending' &&
          object(order.json['handover'])['mode'] == 'self',
    );
    final orderConfirm = wait('OrderConfirmed', order.id);
    await post(salePro, '/merchant/orders/${order.id}/confirm', {});
    await orderConfirm;
    check(
      'sale_not_sold_at_confirmation',
      !(await vehicles.detail(text(sale['slug']))).sold,
    );
    final soldSignal = wait('VehicleStatusChanged', text(sale['id'])),
        fulfilledSignal = wait('OrderCompleted', order.id);
    await post(salePro, '/merchant/orders/${order.id}/fulfil', {});
    await soldSignal;
    await fulfilledSignal;
    check(
      'vehicle_realtime_sold',
      (await vehicles.detail(text(sale['slug']))).sold,
    );
    check(
      'order_realtime_fulfilled',
      (await commerce.detail('orders', order.id)).status == 'fulfilled',
    );
    check(
      'private_histories_and_receipts',
      (await commerce.all('orders')).any((o) => o.id == order.id) &&
          (await commerce.all(
            'reservations',
          )).any((r) => r.id == reservation.id) &&
          (await receipts.list()).items.length == 2,
    );
    final old = await vehicles.detail(text(rental['slug']));
    final newPrice = (old.price('rental')!.amount + BigInt.from(500))
        .toString();
    final priceSignal = wait('VehicleUpdated', text(rental['id']));
    await rentalPro.dio.put<dynamic>(
      '/merchant/vehicles/${rental['id']}',
      data: {'rent_daily_minor': newPrice},
    );
    await priceSignal;
    check(
      'vehicle_price_http_reconciliation',
      (await vehicles.detail(
            text(rental['slug']),
          )).price('rental')!.amount.toString() ==
          newPrice,
    );
    final unpublishSignal = wait('VehicleUnpublished', text(rental['id']));
    await rentalPro.dio.patch<dynamic>(
      '/merchant/vehicles/${rental['id']}/status',
      data: {'publication_status': 'draft'},
    );
    await unpublishSignal;
    var removed = false;
    try {
      await vehicles.detail(text(rental['slug']));
    } catch (e) {
      removed = ApiFailure.from(e).status == 404;
    }
    check('unpublished_http_404', removed);
    transport.foreground(false);
    check(
      'rest_when_socket_stopped',
      (await commerce.detail('orders', order.id)).status == 'fulfilled',
    );
    final reconnected = wait('Reconnected');
    transport.foreground(true);
    await reconnected;
    check('foreground_reconnection', transport.socket != null);
    evidence.addAll({
      'reservation_id': reservation.id,
      'order_id': order.id,
      'rental_vehicle': rental['id'],
      'sale_vehicle': sale['id'],
      'receipt_reference': receipt.reference,
    });
  } catch (e) {
    // Never print Dio request, bearer or registration body.
    stderr.writeln(
      'Integration failed: ${e is StateError ? e.message : ApiFailure.from(e).message}',
    );
    exitCode = 1;
  } finally {
    transport?.dispose();
    await listener?.cancel();
    for (final client in [api, rentalPro, salePro]) {
      if (client.token != null) {
        try {
          await client.dio.post<dynamic>('/auth/logout');
        } catch (_) {}
        client.token = null;
      }
      client.dio.close();
    }
    outsider.dio.close();
    await Directory('qa').create(recursive: true);
    await File('qa/transactions-api.json').writeAsString(
      const JsonEncoder.withIndent('  ').convert({
        'checked_at': DateTime.now().toUtc().toIso8601String(),
        'environment': 'local DEMO',
        'checks': checks,
        'passed': checks.values.where((v) => v).length,
        'failed': exitCode == 0 ? 0 : 1,
        'evidence': evidence,
        'native_app_run': false,
        'native_pdf_share_verified': false,
      }),
    );
  }
}
