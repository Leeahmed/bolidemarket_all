import 'dart:async';
import 'package:bolidemarket/core/api.dart';
import 'package:bolidemarket/core/models.dart';
import 'package:bolidemarket/core/providers.dart';
import 'package:bolidemarket/main.dart';
import 'package:bolidemarket/features/marketplace/filter_sheet.dart';
import 'package:bolidemarket/features/marketplace/query.dart';
import 'package:bolidemarket/features/location/location_sheet.dart';
import 'package:bolidemarket/features/location/location_repository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'support.dart';

class PagedVehicles extends FakeVehicles {
  final next = Completer<PageData<Vehicle>>();
  int calls = 0;
  @override
  Future<PageData<Vehicle>> search(
    SearchQuery query, {
    int page = 1,
    String? shop,
  }) {
    calls++;
    return page == 1
        ? Future.value(PageData([sample()], 1, 2, 2))
        : next.future;
  }
}

class RefusedLocation extends LocationRepository {
  int calls = 0;
  @override
  Future<Json> locate() async {
    calls++;
    throw const FormatException(
      'Localisation refusée. Vous pouvez choisir une ville.',
    );
  }
}

void main() {
  test('Load more concurrent : une requête, fusion et fin', () async {
    final repo = PagedVehicles();
    final state = CatalogueController(repo, const SearchQuery());
    addTearDown(state.dispose);
    await state.reload();
    final more = state.more();
    await state.more();
    expect(repo.calls, 2);
    repo.next.complete(
      PageData(
        [
          Vehicle({...sample().json, 'id': '2'}),
        ],
        2,
        2,
        2,
      ),
    );
    await more;
    expect(state.state.valueOrNull!.items.length, 2);
    await state.more();
    expect(repo.calls, 2);
    expect(state.loadingMore, false);
  });
  testWidgets('Filtres : compteur et budget invalide bloqué', (tester) async {
    await screen(
      tester,
      const FilterSheet(
        SearchQuery(values: {'listing_type': 'sale', 'currency': 'XOF'}),
      ),
      overrides: [vehiclesProvider.overrideWithValue(FakeVehicles())],
      width: 360,
    );
    await tester.pumpAndSettle();
    expect(find.text('Voir 1 résultats'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.widgetWithText(TextField, 'Prix minimum'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Prix minimum'),
      'abc',
    );
    await tester.pumpAndSettle();
    final button = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Recherche…'),
    );
    expect(button.onPressed, isNull);
    expect(tester.takeException(), isNull);
  });
  testWidgets('GPS refusé : choix manuel toujours disponible', (tester) async {
    final location = RefusedLocation();
    await screen(
      tester,
      const LocationSheet(),
      overrides: [
        locationRepositoryProvider.overrideWithValue(location),
        vehiclesProvider.overrideWithValue(FakeVehicles()),
      ],
    );
    await tester.pumpAndSettle();
    expect(location.calls, 0);
    await tester.tap(find.text('Utiliser ma position'));
    await tester.pumpAndSettle();
    expect(location.calls, 1);
    expect(
      find.text('Localisation refusée. Vous pouvez choisir une ville.'),
      findsOneWidget,
    );
    expect(find.text('Pays'), findsOneWidget);
    expect(find.text('Tous les pays'), findsOneWidget);
  });
  testWidgets('Onboarding une fois, guard invité, login puis retour favoris', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final store = MemoryStore()..seen = false;
    final auth = FakeAuth(ApiClient(), store);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authRepositoryProvider.overrideWithValue(auth),
          sessionStoreProvider.overrideWithValue(store),
          countriesProvider.overrideWith((_) async => testCountries),
          configProvider.overrideWith((_) async => {'default_country': 'CI'}),
          favoritesProvider.overrideWith((_) async => [sample()]),
          homeProvider.overrideWith(
            (_) async =>
                HomeData(PageData([], 1, 1, 0), PageData([], 1, 1, 0), []),
          ),
        ],
        child: const BolideMarketApp(),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Commencer'), findsOneWidget);
    await tester.tap(find.text('Commencer'));
    await tester.pumpAndSettle();
    expect(store.seen, true);
    await tester.tap(find.text('Favoris'));
    await tester.pumpAndSettle();
    expect(find.text('Bon retour parmi nous.'), findsOneWidget);
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
    expect(find.text('Mes favoris'), findsOneWidget);
    expect(find.text('Toyota RAV4'), findsOneWidget);
  });
  testWidgets('Carte vers fiche : Hero unique, état frais et retour', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final store = MemoryStore()..token = 'saved';
    final auth = FakeAuth(ApiClient(), store);
    final vehicle = sample();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authRepositoryProvider.overrideWithValue(auth),
          vehiclesProvider.overrideWithValue(FakeVehicles()),
          configProvider.overrideWith((_) async => {'default_country': 'CI'}),
          favoritesProvider.overrideWith((_) async => []),
          homeProvider.overrideWith(
            (_) async => HomeData(
              PageData([vehicle], 1, 1, 1),
              PageData([vehicle], 1, 1, 1),
              [],
            ),
          ),
        ],
        child: const BolideMarketApp(),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Toyota RAV4').first);
    await tester.pumpAndSettle();
    expect(find.text('Votre prochain bolide'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.tap(find.byTooltip('Retour'));
    await tester.pumpAndSettle();
    expect(find.text('Quel sera votre prochain bolide ?'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
