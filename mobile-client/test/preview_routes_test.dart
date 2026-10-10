import 'package:bolidemarket/core/providers.dart';
import 'package:bolidemarket/core/router.dart';
import 'package:bolidemarket/features/dev_preview/config.dart';
import 'package:bolidemarket/features/dev_preview/gallery.dart';
import 'package:bolidemarket/features/dev_preview/providers.dart';
import 'package:bolidemarket/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  if (!visualDemoEnabled) {
    testWidgets('Normal app has neither preview route nor debug overlay', (
      tester,
    ) async {
      final c = ProviderContainer(overrides: demoRepositoryOverrides());
      addTearDown(c.dispose);
      await tester.pumpWidget(
        UncontrolledProviderScope(container: c, child: const BolideMarketApp()),
      );
      await tester.pumpAndSettle();
      expect(find.byType(VisualDemoFrame), findsNothing);
      c.read(routerProvider).go('/dev-preview');
      await tester.pumpAndSettle();
      expect(find.byType(DevPreviewGallery), findsNothing);
      expect(find.text('Retour à l’accueil'), findsOneWidget);
      expect(tester.takeException(), null);
    });
    return;
  }
  for (final size in [
    const Size(360, 800),
    const Size(390, 844),
    const Size(430, 932),
  ]) {
    testWidgets('Real preview gallery, auth and screens ${size.width}', (
      tester,
    ) async {
      tester.view.physicalSize = size;
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final c = ProviderContainer(overrides: visualDemoOverrides());
      addTearDown(c.dispose);
      await tester.pumpWidget(
        UncontrolledProviderScope(container: c, child: const BolideMarketApp()),
      );
      await tester.pumpAndSettle();
      expect(find.byType(DevPreviewGallery), findsOneWidget);
      await tester.tap(find.text('Guest'));
      await tester.pumpAndSettle();
      expect(c.read(authProvider).valueOrNull, null);
      c.read(routerProvider).go('/account');
      await tester.pumpAndSettle();
      expect(
        c.read(routerProvider).routeInformationProvider.value.uri.path,
        '/login',
      );
      c.read(routerProvider).go('/dev-preview');
      await tester.pumpAndSettle();
      await tester.tap(find.text('Client connecté'));
      await tester.pumpAndSettle();
      expect(c.read(authProvider).value!.firstName, 'Djak');
      for (final path in [
        '/dev-preview/splash',
        '/onboarding',
        '/login',
        '/register',
        '/home',
        '/marketplace',
        '/vehicle/toyota-rav4',
        '/vehicle/peugeot-208',
        '/vehicle/kia-sportage',
        '/account',
        '/profile',
        '/shop/abidjan-prestige-motors',
        '/account/favorites',
        '/account/reservations',
        '/account/orders',
        '/account/receipts',
        '/dev-preview/filters',
        '/vehicle/peugeot-208/reserve',
        '/dev-preview/rental/1',
        '/dev-preview/sale/1',
      ]) {
        c.read(routerProvider).go(path);
        await tester.pumpAndSettle();
        expect(tester.takeException(), null, reason: path);
      }
      await tester.pumpWidget(const SizedBox());
      await tester.pumpAndSettle();
    });
  }
  for (final rental in [true, false]) {
    testWidgets(
      'Real payment → confirmation → immutable receipt, local ${rental ? 'rental' : 'sale'}',
      (tester) async {
        tester.view.physicalSize = const Size(390, 844);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        final c = ProviderContainer(overrides: visualDemoOverrides());
        addTearDown(c.dispose);
        await tester.pumpWidget(
          UncontrolledProviderScope(
            container: c,
            child: const BolideMarketApp(),
          ),
        );
        await tester.pumpAndSettle();
        c
            .read(routerProvider)
            .go('/dev-preview/${rental ? 'rental' : 'sale'}/2');
        await tester.pumpAndSettle();
        await tester.scrollUntilVisible(find.byType(CheckboxListTile), 350);
        await tester.pumpAndSettle();
        await tester.tap(find.byType(CheckboxListTile));
        await tester.pumpAndSettle();
        final confirm = find.text('Confirmer la demande DEMO');
        await tester.scrollUntilVisible(confirm, 250);
        await tester.pumpAndSettle();
        await tester.tap(confirm);
        await tester.pumpAndSettle();
        expect(
          c.read(routerProvider).routeInformationProvider.value.uri.path,
          startsWith('/${rental ? 'reservation' : 'order'}-confirmation/'),
        );
        final receipt = find.text('Voir / télécharger mon reçu');
        await tester.scrollUntilVisible(receipt, 350);
        await tester.pumpAndSettle();
        await tester.tap(receipt);
        await tester.pumpAndSettle();
        expect(find.text('Reçu de démonstration'), findsOneWidget);
        expect(find.text('Djak Kouadou'), findsOneWidget);
        await tester.scrollUntilVisible(find.text('Total'), 350);
        await tester.pumpAndSettle();
        expect(
          find.text(rental ? '135 000 FCFA' : '18 500 000 FCFA'),
          findsWidgets,
        );
        expect(tester.takeException(), null);
        await tester.pumpWidget(const SizedBox());
        await tester.pumpAndSettle();
      },
    );
  }
}
