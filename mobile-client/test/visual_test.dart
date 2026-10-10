import 'dart:io';
import 'package:bolidemarket/core/models.dart';
import 'package:bolidemarket/core/providers.dart';
import 'package:bolidemarket/core/router.dart';
import 'package:bolidemarket/features/home/home_screen.dart';
import 'package:bolidemarket/features/auth/auth_screen.dart';
import 'package:bolidemarket/features/account/account_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'support.dart';

Future<void> photos(WidgetTester tester) async {
  await tester.runAsync(() async {
    for (final asset in [
      'logo-light.webp',
      'logo-dark.png',
      'auth-login.webp',
      'vehicle-rav4.webp',
      'vehicle-208.webp',
      'vehicle-c300.webp',
    ]) {
      await precacheImage(
        AssetImage('assets/images/$asset'),
        tester.element(find.byType(MaterialApp)),
      );
    }
  });
  await tester.pumpAndSettle();
}

void main() {
  setUpAll(() async {
    for (final name in ['Inter', 'Sora']) {
      final loader = FontLoader(name)
        ..addFont(rootBundle.load('assets/fonts/$name.ttf'));
      await loader.load();
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
    testWidgets('Capture réelle Flutter accueil $width', (tester) async {
      final vehicles = [
        Vehicle({
          ...sample().json,
          'primary_image': {'is_placeholder': true},
        }),
        Vehicle({
          ...sample(rental: true).json,
          'id': '2',
          'slug': 'peugeot-208',
          'brand': {'name': 'Peugeot'},
          'model': {'name': '208'},
          'primary_image': {'is_placeholder': true},
        }),
        Vehicle({
          ...sample().json,
          'id': '3',
          'slug': 'mercedes-c300',
          'brand': {'name': 'Mercedes-Benz'},
          'model': {'name': 'C300'},
          'primary_image': {'is_placeholder': true},
        }),
      ];
      final home = HomeData(
        PageData(vehicles, 1, 1, 3),
        PageData([vehicles[1]], 1, 1, 1),
        [],
      );
      await screen(
        tester,
        RepaintBoundary(
          key: const ValueKey('capture'),
          child: const AppShell('/home', HomeScreen()),
        ),
        width: width,
        overrides: [homeProvider.overrideWith((_) async => home)],
      );
      await photos(tester);
      await expectLater(
        find.byKey(const ValueKey('capture')),
        matchesGoldenFile('goldens/home-${width.toInt()}.png'),
      );
    });
  }
  testWidgets('Capture authentification avec fontes officielles', (
    tester,
  ) async {
    await screen(
      tester,
      RepaintBoundary(
        key: const ValueKey('capture'),
        child: const AuthScreen(),
      ),
      loggedIn: false,
    );
    await photos(tester);
    await expectLater(
      find.byKey(const ValueKey('capture')),
      matchesGoldenFile('goldens/login-390.png'),
    );
  });
  testWidgets('Capture espace client', (tester) async {
    await screen(
      tester,
      RepaintBoundary(
        key: const ValueKey('capture'),
        child: const AppShell('/account', AccountScreen()),
      ),
      overrides: [
        vehiclesProvider.overrideWithValue(FakeVehicles()),
        accountProvider.overrideWith(
          (_) async => {
            'reservations_total': 0,
            'orders_total': 0,
            'reservations': [],
          },
        ),
      ],
    );
    await photos(tester);
    await expectLater(
      find.byKey(const ValueKey('capture')),
      matchesGoldenFile('goldens/account-390.png'),
    );
  });
}
