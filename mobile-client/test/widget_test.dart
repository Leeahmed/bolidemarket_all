import 'package:bolidemarket/core/api.dart';
import 'package:bolidemarket/core/models.dart';
import 'package:bolidemarket/core/providers.dart';
import 'package:bolidemarket/features/auth/auth_screen.dart';
import 'package:bolidemarket/features/home/home_screen.dart';
import 'package:bolidemarket/features/marketplace/marketplace_screen.dart';
import 'package:bolidemarket/features/vehicle/vehicle_screen.dart';
import 'package:bolidemarket/features/account/account_screen.dart';
import 'package:bolidemarket/features/profile/profile_screen.dart';
import 'package:bolidemarket/features/shop/shop_screen.dart';
import 'package:bolidemarket/shared/widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'support.dart';

void main() {
  testWidgets('Connexion : champs, validation et navigation', (tester) async {
    await screen(tester, const AuthScreen(), loggedIn: false);
    expect(find.text('Bon retour parmi nous.'), findsOneWidget);
    await tester.tap(find.text('Se connecter'));
    await tester.pump();
    expect(find.text('Champ requis'), findsNWidgets(2));
    await tester.enterText(
      find.byType(TextFormField).at(0),
      'aya@example.test',
    );
    await tester.enterText(
      find.byType(TextFormField).at(1),
      'DemoPassword!123',
    );
    await tester.tap(find.text('Se connecter'));
    await tester.pumpAndSettle();
    expect(find.text('Destination compte'), findsOneWidget);
  });
  testWidgets('Inscription client : pays téléphone confirmation', (
    tester,
  ) async {
    await screen(tester, const AuthScreen(register: true), loggedIn: false);
    await tester.pumpAndSettle();
    expect(find.text('Prénom'), findsOneWidget);
    expect(find.text('Nom'), findsOneWidget);
    expect(find.text('Côte d’Ivoire'), findsOneWidget);
    expect(find.text('Indicatif : +225'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Créer mon compte'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Créer mon compte'));
    await tester.pump();
    expect(find.text('Champ requis'), findsWidgets);
  });
  for (final width in [360.0, 390.0, 430.0, 768.0]) {
    testWidgets('Accueil et cartes sans overflow $width', (tester) async {
      final home = HomeData(
        PageData([sample()], 1, 1, 1),
        PageData([sample(rental: true)], 1, 1, 1),
        [],
      );
      await screen(
        tester,
        const HomeScreen(),
        width: width,
        overrides: [homeProvider.overrideWith((_) async => home)],
      );
      await tester.pumpAndSettle();
      expect(find.text('Bonjour, Aya 👋'), findsOneWidget);
      expect(find.text('Quel sera votre prochain bolide ?'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.scrollUntilVisible(
        find.text('45 000 FCFA / jour'),
        350,
        scrollable: find.byType(Scrollable).first,
      );
      expect(tester.takeException(), isNull);
    });
  }
  testWidgets('Carte vente : prix devise et intention', (tester) async {
    await screen(tester, SizedBox(width: 350, child: VehicleCard(sample())));
    await tester.pumpAndSettle();
    expect(find.text('À vendre'), findsOneWidget);
    expect(find.text('18 500 000 FCFA'), findsOneWidget);
  });
  testWidgets('Carte location : unité / jour', (tester) async {
    await screen(
      tester,
      SizedBox(width: 350, child: VehicleCard(sample(rental: true))),
    );
    await tester.pumpAndSettle();
    expect(find.text('À louer'), findsOneWidget);
    expect(find.text('45 000 FCFA / jour'), findsOneWidget);
  });
  testWidgets('Favori invité renvoie à connexion', (tester) async {
    await screen(
      tester,
      SizedBox(width: 350, child: VehicleCard(sample())),
      loggedIn: false,
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Ajouter aux favoris'));
    await tester.pumpAndSettle();
    expect(find.text('Destination connexion'), findsOneWidget);
  });
  testWidgets('Catalogue erreur réseau + réessayer', (tester) async {
    final repo = FakeVehicles()
      ..failure = const ApiFailure(
        'Impossible de se connecter à BolideMarket.',
      );
    await screen(
      tester,
      const MarketplaceScreen(),
      overrides: [vehiclesProvider.overrideWithValue(repo)],
    );
    await tester.pumpAndSettle();
    expect(
      find.text('Impossible de se connecter à BolideMarket.'),
      findsOneWidget,
    );
    repo.failure = null;
    await tester.tap(find.text('Réessayer'));
    await tester.pumpAndSettle();
    expect(find.text('1 résultats'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets('Catalogue chargement léger', (tester) async {
    await screen(tester, const LoadingCards());
    expect(find.byType(LoadingCards), findsOneWidget);
    expect(find.bySemanticsLabel('Chargement'), findsOneWidget);
  });
  testWidgets('Fiche SOLD : CTA désactivé', (tester) async {
    await screen(
      tester,
      const VehicleScreen('toyota-rav4'),
      overrides: [
        vehicleProvider(
          'toyota-rav4',
        ).overrideWith((_) async => sample(sold: true)),
        similarProvider('toyota-rav4').overrideWith((_) async => []),
      ],
    );
    await tester.pumpAndSettle();
    final button = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Vendu'),
    );
    expect(button.onPressed, isNull);
    expect(tester.takeException(), isNull);
  });
  testWidgets('Compte : stats réelles + retour marché', (tester) async {
    await screen(
      tester,
      const AccountScreen(),
      overrides: [
        vehiclesProvider.overrideWithValue(FakeVehicles()),
        accountProvider.overrideWith(
          (_) async => {
            'reservations_total': 2,
            'orders_total': 1,
            'reservations': [],
          },
        ),
      ],
    );
    await tester.pumpAndSettle();
    expect(find.text('Trouver votre prochain bolide'), findsOneWidget);
    expect(find.text('2'), findsOneWidget);
    expect(find.text('Aya Demo'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets('Favoris affiche données API', (tester) async {
    await screen(
      tester,
      const FavoritesScreen(),
      overrides: [
        favoritesProvider.overrideWith((_) async => [sample()]),
      ],
    );
    await tester.pumpAndSettle();
    expect(find.text('Toyota RAV4'), findsOneWidget);
  });
  testWidgets('Profil affiche avatar galerie pays ville devise', (
    tester,
  ) async {
    await screen(
      tester,
      const ProfileScreen(),
      overrides: [
        vehiclesProvider.overrideWithValue(FakeVehicles()),
        accountRepositoryProvider.overrideWithValue(FakeAccount()),
      ],
    );
    await tester.pumpAndSettle();
    expect(find.text('Choisir dans la galerie'), findsOneWidget);
    expect(find.textContaining('Devise locale : XOF'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets('Boutique affiche filtres et catalogue', (tester) async {
    await screen(
      tester,
      const ShopScreen('demo-shop'),
      overrides: [
        vehiclesProvider.overrideWithValue(FakeVehicles()),
        shopProvider('demo-shop').overrideWith(
          (_) async => {
            'name': 'Demo Shop',
            'sale_vehicles_count': 1,
            'rental_vehicles_count': 1,
          },
        ),
      ],
    );
    await tester.pumpAndSettle();
    expect(find.text('Vente & Location'), findsOneWidget);
    expect(find.text('Disponibles'), findsOneWidget);
    expect(find.text('Toyota RAV4'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
