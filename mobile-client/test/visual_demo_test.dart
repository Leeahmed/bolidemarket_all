import 'dart:convert';
import 'package:bolidemarket/core/api.dart';
import 'package:bolidemarket/core/models.dart';
import 'package:bolidemarket/core/providers.dart';
import 'package:bolidemarket/features/commerce/models.dart';
import 'package:bolidemarket/features/commerce/providers.dart';
import 'package:bolidemarket/features/dev_preview/config.dart';
import 'package:bolidemarket/features/dev_preview/fixtures.dart';
import 'package:bolidemarket/features/dev_preview/providers.dart';
import 'package:bolidemarket/features/dev_preview/repositories.dart';
import 'package:bolidemarket/features/marketplace/query.dart';
import 'package:bolidemarket/features/realtime/gate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'Bypass requires all three conditions, never profile/release/production',
    () {
      for (final debug in [false, true]) {
        for (final requested in [false, true]) {
          for (final environment in ['development', 'staging', 'production']) {
            expect(
              allowVisualDemo(
                debug: debug,
                requested: requested,
                environment: environment,
              ),
              debug && requested && environment == 'development',
            );
          }
        }
      }
    },
  );
  test('Normal providers retain API repositories and secure store', () {
    final c = ProviderContainer();
    addTearDown(c.dispose);
    expect(c.read(apiProvider), isNot(isA<DemoApiClient>()));
    expect(c.read(authRepositoryProvider), isNot(isA<DemoAuthRepository>()));
    expect(c.read(vehiclesProvider), isNot(isA<DemoVehicleRepository>()));
    expect(
      c.read(commerceRepositoryProvider),
      isNot(isA<DemoCommerceRepository>()),
    );
    expect(
      c.read(receiptRepositoryProvider),
      isNot(isA<DemoReceiptRepository>()),
    );
    expect(c.read(sessionStoreProvider), isNot(isA<DemoSessionStore>()));
  });
  test(
    'Injected local data/auth/account/favorites work without network',
    () async {
      final c = ProviderContainer(overrides: demoRepositoryOverrides());
      addTearDown(c.dispose);
      expect(c.read(authProvider).value!.name, 'Djak Kouadou');
      expect(c.read(authProvider).value!.location, 'Cocody, Abidjan');
      final home = await c.read(homeProvider.future);
      expect(home.nearby.total, 5);
      expect(home.newest.total, 6);
      expect(home.shops, hasLength(4));
      expect(
        (await c.read(
          vehicleProvider('toyota-rav4').future,
        )).price('sale')!.format(),
        '18 500 000 FCFA',
      );
      expect(
        (await c.read(
          vehicleProvider('mercedes-c300').future,
        )).price('sale')!.format(),
        '27 900 000 FCFA',
      );
      final favorite = c.read(favoriteRepositoryProvider);
      await favorite.set('3', true);
      expect(await favorite.all(), hasLength(3));
      await favorite.set('3', false);
      expect(await favorite.all(), hasLength(2));
      final account = await c.read(accountRepositoryProvider).overview();
      expect(account['orders_total'], 2);
      expect(account['reservations_total'], 1);
      await c.read(authProvider.notifier).logout();
      expect(c.read(authProvider).valueOrNull, null);
      expect(c.read(apiProvider).token, null);
      await expectLater(
        c.read(receiptRepositoryProvider).list(),
        throwsA(isA<ApiFailure>()),
      );
      await c
          .read(authProvider.notifier)
          .login('demo@example.test', 'not-persisted');
      expect(c.read(authProvider).value!.firstName, 'Djak');
      expect(c.read(sessionStoreProvider), isA<DemoSessionStore>());
      expect(c.read(realtimeProvider), isA<DemoRealtimeTransport>());
      await expectLater(
        c.read(apiProvider).get('/auth/me'),
        throwsA(isA<ApiFailure>()),
      );
      await expectLater(
        c.read(apiProvider).dio.post<dynamic>('/orders'),
        throwsA(anything),
      );
    },
  );
  test(
    'Search, offer, currency, model, sort and pagination are local and coherent',
    () async {
      final repo = DemoVehicleRepository(DemoApiClient(), DemoState());
      expect(
        (await repo.search(const SearchQuery(values: {'q': 'toyota'}))).total,
        1,
      );
      expect(
        (await repo.search(
          const SearchQuery(values: {'listing_type': 'rental'}),
        )).total,
        2,
      );
      expect(
        (await repo.search(
          const SearchQuery(values: {'status': 'sold'}),
        )).items.single.title,
        'Kia Sportage',
      );
      expect(
        (await repo.search(
          const SearchQuery(values: {'brand_id': 2}),
        )).items.single.title,
        'Peugeot 208',
      );
      expect(
        (await repo.search(
          const SearchQuery(values: {'country_code': 'FR'}),
        )).total,
        0,
      );
      final sorted = await repo.search(
        const SearchQuery(
          values: {
            'listing_type': 'sale',
            'currency': 'XOF',
            'sort': 'price_asc',
          },
        ),
      );
      expect(sorted.items.first.title, 'Renault Kangoo');
      expect(
        (await repo.search(
          const SearchQuery(
            values: {
              'listing_type': 'sale',
              'currency': 'XOF',
              'min_price': '18000000',
            },
          ),
        )).total,
        3,
      );
      await expectLater(
        repo.search(const SearchQuery(values: {'min_price': '5'})),
        throwsFormatException,
      );
      expect((await repo.search(const SearchQuery(), page: 2)).items, isEmpty);
    },
  );
  test(
    'Local rental flow, idempotency, calendar, cancellation, immutable receipt/PDF',
    () async {
      final data = DemoState(), api = DemoApiClient();
      final repo = DemoCommerceRepository(api, data);
      final receipts = DemoReceiptRepository(api, data);
      final today = shopToday('Africa/Abidjan');
      final start = today.add(const Duration(days: 1)),
          end = today.add(const Duration(days: 4));
      final q = await repo.quote('2', start, end);
      expect(q['total_minor'], '135000');
      final request = reservationRequest(text(q['id']), DemoPayment.card);
      final record = await repo.reserve(request, 'rental-intent');
      expect(record.status, 'confirmed');
      expect(record.total.format(), '135 000 FCFA');
      expect((await repo.reserve(request, 'rental-intent')).id, record.id);
      await expectLater(
        repo.reserve({
          ...request,
          'payment_method': 'CASH_DEMO',
        }, 'rental-intent'),
        throwsA(isA<ApiFailure>()),
      );
      expect(
        (await repo.availability('peugeot-208')).validRange(start, end),
        false,
      );
      final receipt = await receipts.detail(record.receipt);
      expect(receipt.buyer['name'], 'Djak Kouadou');
      expect(receipt.total.amount, record.total.amount);
      expect(object(object(record.json['order'])['payment'])['status'], 'paid');
      expect(
        (await repo.detail('orders', record.id)).reference,
        receipt.transaction['order_reference'],
      );
      data.user['first_name'] = 'Modifié';
      expect(
        (await receipts.detail(record.receipt)).buyer['name'],
        'Djak Kouadou',
      );
      final bytes = await receipts.pdf(record.receipt);
      expect(ascii.decode(bytes), startsWith('%PDF-1.4'));
      expect(ascii.decode(bytes), contains('Aucun paiement reel'));
      expect((await repo.cancel(record.id)).status, 'cancelled');
      expect(
        (await repo.availability('peugeot-208')).validRange(start, end),
        true,
      );
      expect(
        jsonEncode((await receipts.detail(record.receipt)).json),
        jsonEncode(receipt.json),
      );
    },
  );
  test(
    'Local sale requires available sale vehicle and creates matching receipt',
    () async {
      final data = DemoState(), api = DemoApiClient();
      final repo = DemoCommerceRepository(api, data);
      final request = orderRequest('3', DemoPayment.transfer, {
        'mode': 'self',
        'scheduled_local':
            '${dateInput(shopToday('Africa/Abidjan').add(const Duration(days: 1)))}T12:00',
        'contact_name': 'Djak Kouadou',
        'contact_phone': '+2250700000000',
      });
      final order = await repo.buy(request, 'sale-intent');
      final receipt = await DemoReceiptRepository(
        api,
        data,
      ).detail(order.receipt);
      expect(order.total.format(), '27 900 000 FCFA');
      expect(receipt.total.amount, order.total.amount);
      expect((await repo.detail('orders', order.reference)).id, order.id);
      await expectLater(
        repo.buy(orderRequest('4', DemoPayment.cash, {'mode': 'self'}), 'sold'),
        throwsA(isA<ApiFailure>()),
      );
    },
  );
}
