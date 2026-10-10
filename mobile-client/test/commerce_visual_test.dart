import 'dart:io';
import 'package:bolidemarket/core/providers.dart';
import 'package:bolidemarket/core/models.dart';
import 'package:bolidemarket/features/commerce/history_screen.dart';
import 'package:bolidemarket/features/commerce/receipts_screen.dart';
import 'package:bolidemarket/features/commerce/providers.dart';
import 'package:bolidemarket/features/commerce/workflow_screen.dart';
import 'package:bolidemarket/features/commerce/widgets.dart';
import 'package:bolidemarket/features/commerce/models.dart';
import 'package:bolidemarket/features/account/account_screen.dart';
import 'package:flutter/material.dart';
import 'package:clock/clock.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'commerce_support.dart';
import 'support.dart';
import 'visual_test.dart' show photos;

void main() {
  setUpAll(() async {
    for (final name in ['Inter', 'Sora']) {
      await (FontLoader(
        name,
      )..addFont(rootBundle.load('assets/fonts/$name.ttf'))).load();
    }
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
    final emoji = File('C:/Windows/Fonts/seguiemj.ttf');
    if (await emoji.exists()) {
      await (FontLoader('Segoe UI Emoji')..addFont(
            Future.value(ByteData.sublistView(await emoji.readAsBytes())),
          ))
          .load();
    }
  });
  for (final width in [360.0, 390.0, 430.0, 768.0]) {
    testWidgets(
      'Transactions compactes sans débordement $width',
      (
        tester,
      ) => withClock(Clock.fixed(DateTime.utc(2026, 10, 10, 12)), () async {
        final repo = FakeCommerce();
        final vehicles = FakeVehicles()
          ..items = [
            Vehicle({
              ...rentalVehicle().json,
              'offer_types': ['sale', 'rent'],
              'primary_image': {'is_placeholder': true},
              'sale_price': sample().json['sale_price'],
            }),
          ];
        final views = <String, Widget>{
          'dates': const CommerceWorkflowScreen('toyota-rav4', rental: true),
          'purchase': const CommerceWorkflowScreen(
            'toyota-rav4',
            rental: false,
          ),
          'payment': const Scaffold(
            body: SingleChildScrollView(
              padding: EdgeInsets.all(24),
              child: PaymentChoices(DemoPayment.card, ignorePayment),
            ),
          ),
          'reservation': const TransactionScreen(
            'reservations',
            '12',
            confirmation: true,
          ),
          'history': const Scaffold(body: HistoryScreen('reservations')),
          'receipt': const ReceiptDetailScreen('BM-RCP-2026-QA'),
          'account': const Scaffold(body: AccountScreen()),
        };
        for (final entry in views.entries) {
          await screen(
            tester,
            RepaintBoundary(key: const ValueKey('capture'), child: entry.value),
            width: width,
            french: true,
            overrides: [
              vehiclesProvider.overrideWithValue(vehicles),
              commerceRepositoryProvider.overrideWithValue(repo),
              receiptRepositoryProvider.overrideWithValue(FakeReceipts()),
              availabilityProvider(
                'toyota-rav4',
              ).overrideWith((_) async => openAvailability()),
              accountProvider.overrideWith(
                (_) async => {
                  'reservations_total': 1,
                  'orders_total': 1,
                  'reservations': [recordJson()],
                  'orders': [recordJson(rental: false)],
                  'receipts': [receiptJson()],
                },
              ),
              accountSuggestionsProvider.overrideWith((_) async => []),
            ],
          );
          await photos(tester);
          expect(tester.takeException(), isNull, reason: entry.key);
          await expectLater(
            find.byKey(const ValueKey('capture')),
            matchesGoldenFile('goldens/8b-${entry.key}-${width.toInt()}.png'),
          );
        }
      }),
    );
  }
}

void ignorePayment(DemoPayment payment) {}
