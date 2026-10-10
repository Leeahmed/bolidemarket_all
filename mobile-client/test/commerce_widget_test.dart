import 'dart:async';
import 'package:bolidemarket/core/api.dart';
import 'package:bolidemarket/core/models.dart';
import 'package:bolidemarket/core/providers.dart';
import 'package:bolidemarket/core/theme.dart';
import 'package:bolidemarket/features/commerce/calendar.dart';
import 'package:bolidemarket/features/commerce/history_screen.dart';
import 'package:bolidemarket/features/commerce/models.dart';
import 'package:bolidemarket/features/commerce/providers.dart';
import 'package:bolidemarket/features/commerce/receipts_screen.dart';
import 'package:bolidemarket/features/commerce/widgets.dart';
import 'package:bolidemarket/features/commerce/workflow_screen.dart';
import 'package:bolidemarket/features/realtime/gate.dart';
import 'package:bolidemarket/features/realtime/reverb.dart';
import 'package:bolidemarket/features/vehicle/vehicle_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'commerce_support.dart';
import 'support.dart';

Future<void> press(WidgetTester tester, String label) async {
  final button = find.widgetWithText(FilledButton, label);
  await tester.scrollUntilVisible(
    button,
    300,
    scrollable: find.byType(Scrollable).first,
  );
  await tester.pumpAndSettle();
  await tester.tap(button);
  await tester.pumpAndSettle();
}

Future<void> paymentStep(
  WidgetTester tester,
  FakeCommerce repo, {
  bool rental = true,
}) async {
  final auth = FakeAuth(ApiClient(), MemoryStore());
  final vehicles = FakeVehicles()
    ..items = [
      rental
          ? rentalVehicle()
          : Vehicle({...sample().json, 'shop': rentalVehicle().json['shop']}),
    ];
  final router = GoRouter(
    initialLocation: '/vehicle/toyota-rav4/${rental ? 'reserve' : 'buy'}',
    routes: [
      GoRoute(
        path: '/vehicle/:slug/:action',
        builder: (_, state) => CommerceWorkflowScreen(
          state.pathParameters['slug']!,
          rental: state.pathParameters['action'] == 'reserve',
        ),
      ),
      GoRoute(
        path: '/reservation-confirmation/:reference',
        builder: (_, state) => TransactionScreen(
          'reservations',
          state.pathParameters['reference']!,
          confirmation: true,
        ),
      ),
      GoRoute(
        path: '/order-confirmation/:reference',
        builder: (_, state) => TransactionScreen(
          'orders',
          state.pathParameters['reference']!,
          confirmation: true,
        ),
      ),
      GoRoute(
        path: '/account/reservations',
        builder: (_, _) => const HistoryScreen('reservations'),
      ),
      GoRoute(
        path: '/marketplace',
        builder: (_, _) => const Scaffold(body: Text('Marché')),
      ),
    ],
  );
  addTearDown(router.dispose);
  tester.view.physicalSize = const Size(390, 1000);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        authRepositoryProvider.overrideWithValue(auth),
        authProvider.overrideWith(
          (ref) => AuthController(auth)..setUser(testUser),
        ),
        vehiclesProvider.overrideWithValue(vehicles),
        commerceRepositoryProvider.overrideWithValue(repo),
        availabilityProvider(
          'toyota-rav4',
        ).overrideWith((_) async => openAvailability()),
        accountProvider.overrideWith((_) async => {}),
      ],
      child: MaterialApp.router(
        theme: appTheme(),
        locale: const Locale('fr'),
        supportedLocales: const [Locale('fr')],
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        routerConfig: router,
      ),
    ),
  );
  await tester.pumpAndSettle();
  final today = shopToday('Africa/Abidjan');
  if (rental) {
    for (final offset in [1, 5]) {
      final day = today.add(Duration(days: offset));
      var button = find.byKey(ValueKey('day:${dateInput(day)}'));
      while (button.evaluate().isEmpty) {
        await tester.tap(find.byTooltip('Mois suivant'));
        await tester.pumpAndSettle();
        button = find.byKey(ValueKey('day:${dateInput(day)}'));
      }
      await tester.ensureVisible(button);
      await tester.tap(button);
      await tester.pumpAndSettle();
    }
    await press(tester, 'Continuer');
    expect(find.text('180 000 FCFA'), findsWidgets);
  } else {
    await press(tester, 'Continuer');
    final date = find.widgetWithText(TextButton, 'Choisir la date');
    await tester.scrollUntilVisible(
      date,
      250,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(date);
    await tester.pumpAndSettle();
    final meeting = today.add(const Duration(days: 2));
    if (meeting.month != today.month) {
      await tester.tap(find.byTooltip('Next month'));
      await tester.pumpAndSettle();
    }
    await tester.tap(
      find
          .descendant(
            of: find.byType(DatePickerDialog),
            matching: find.text(meeting.day.toString()),
          )
          .last,
    );
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
    final time = find.widgetWithText(
      TextButton,
      'Choisir l’heure approximative',
    );
    await tester.ensureVisible(time);
    await tester.tap(time);
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
  }
  await press(tester, 'Continuer');
  final consent = find.byType(CheckboxListTile);
  await tester.ensureVisible(consent);
  await tester.tap(consent);
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('Achat complet : remise personnelle et payload DEMO serveur', (
    tester,
  ) async {
    final repo = FakeCommerce()..row = recordJson(rental: false);
    await paymentStep(tester, repo, rental: false);
    await press(tester, 'Confirmer la demande DEMO');
    expect(repo.submitted.single['payment_method'], 'MOBILE_MONEY_DEMO');
    expect(object(repo.submitted.single['handover'])['mode'], 'self');
    expect(
      object(repo.submitted.single['handover'])['contact_phone'],
      '+2250701234567',
    );
    expect(repo.submitted.single.containsKey('total_minor'), false);
    expect(find.text('Achat enregistré'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets('Lien achat incompatible : aucun crash ni CTA actif', (
    tester,
  ) async {
    await screen(
      tester,
      const CommerceWorkflowScreen('toyota-rav4', rental: false),
      overrides: [
        vehiclesProvider.overrideWithValue(
          FakeVehicles()..items = [rentalVehicle()],
        ),
      ],
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    final button = find.widgetWithText(FilledButton, 'Continuer');
    await tester.scrollUntilVisible(
      button,
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(tester.widget<FilledButton>(button).onPressed, isNull);
  });
  testWidgets(
    'Calendrier : bloque les jours indisponibles et accepte le retour adjacent',
    (tester) async {
      final today = shopToday('Africa/Abidjan');
      final block = today.add(const Duration(days: 3));
      final availability = Availability({
        ...openAvailability().json,
        'intervals': [
          {
            'starts_at': block.toIso8601String(),
            'ends_at': block.add(const Duration(days: 2)).toIso8601String(),
          },
        ],
      });
      DateTime? start, end;
      await screen(
        tester,
        StatefulBuilder(
          builder: (context, setState) => AvailabilityCalendar(
            availability: availability,
            start: start,
            end: end,
            onChanged: (a, b) => setState(() {
              start = a;
              end = b;
            }),
          ),
        ),
      );
      await tester.pumpAndSettle();
      if (block.month != today.month) {
        await tester.tap(find.byTooltip('Mois suivant'));
        await tester.pumpAndSettle();
      }
      final disabled = find.byKey(ValueKey('day:${dateInput(block)}'));
      expect(tester.widget<TextButton>(disabled).onPressed, isNull);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('Paiement DEMO : quatre modes, aucune saisie de carte ni OTP', (
    tester,
  ) async {
    await screen(tester, PaymentChoices(DemoPayment.card, (_) {}));
    expect(find.text('Carte bancaire'), findsOneWidget);
    expect(find.text('Mobile Money'), findsOneWidget);
    expect(find.text('Virement bancaire'), findsOneWidget);
    expect(find.byType(TextField), findsNothing);
    expect(find.textContaining('Aucun paiement réel'), findsOneWidget);
  });
  testWidgets(
    'Réservation complète : devis serveur, double clic bloqué et confirmation pending',
    (tester) async {
      final repo = FakeCommerce()..pending = Completer<CommerceRecord>();
      await paymentStep(tester, repo);
      final confirm = find.widgetWithText(
        FilledButton,
        'Confirmer la demande DEMO',
      );
      await tester.ensureVisible(confirm);
      await tester.tap(confirm);
      await tester.pump();
      expect(repo.submitted.length, 1);
      expect(repo.submitted.first, {
        'quote_id': 'quote-1',
        'payment_method': 'MOBILE_MONEY_DEMO',
      });
      expect(
        tester
            .widget<FilledButton>(
              find.widgetWithText(FilledButton, 'Enregistrement…'),
            )
            .onPressed,
        isNull,
      );
      repo.pending!.complete(CommerceRecord(recordJson()));
      await tester.pumpAndSettle();
      expect(find.text('Réservation enregistrée'), findsOneWidget);
      expect(find.text('En attente'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.textContaining('Le reçu sera disponible'),
        250,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.textContaining('Le reçu sera disponible'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('Réservation réseau incertain : retry conserve clé et payload', (
    tester,
  ) async {
    final repo = FakeCommerce();
    await paymentStep(tester, repo);
    repo.failure = const ApiFailure('Timeout');
    await press(tester, 'Confirmer la demande DEMO');
    expect(find.textContaining('La réponse n’a pas été reçue'), findsOneWidget);
    repo.failure = null;
    await press(tester, 'Réessayer la même demande');
    expect(repo.submitted.length, 2);
    expect(repo.keys[0], repo.keys[1]);
    expect(repo.submitted[0], repo.submitted[1]);
    expect(find.text('Réservation enregistrée'), findsOneWidget);
  });
  testWidgets(
    'Conflit réservation : message, calendrier rechargé et dates effacées',
    (tester) async {
      final repo = FakeCommerce();
      await paymentStep(tester, repo);
      repo.failure = const ApiFailure(
        'Conflict',
        status: 409,
        code: 'VEHICLE_UNAVAILABLE',
      );
      await press(tester, 'Confirmer la demande DEMO');
      expect(
        find.text('Ces dates viennent de devenir indisponibles.'),
        findsOneWidget,
      );
      expect(find.textContaining('Étape 1'), findsOneWidget);
      expect(find.textContaining('Départ : À choisir'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('Résumé achat : véhicule, vendeur et prix exact', (tester) async {
    await screen(
      tester,
      const CommerceWorkflowScreen('toyota-rav4', rental: false),
      overrides: [vehiclesProvider.overrideWithValue(FakeVehicles())],
    );
    await tester.pumpAndSettle();
    expect(find.text('Toyota RAV4'), findsOneWidget);
    expect(find.text('18 500 000 FCFA'), findsOneWidget);
    expect(find.text('Demo Shop'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets('Confirmation achat : historique, statut et paiement distinct', (
    tester,
  ) async {
    final repo = FakeCommerce()..row = recordJson(rental: false);
    await screen(
      tester,
      const TransactionScreen('orders', '12', confirmation: true),
      overrides: [
        commerceRepositoryProvider.overrideWithValue(repo),
        vehiclesProvider.overrideWithValue(FakeVehicles()),
      ],
    );
    await tester.pumpAndSettle();
    expect(find.text('Achat enregistré'), findsOneWidget);
    expect(
      find.text('En attente de confirmation professionnelle'),
      findsOneWidget,
    );
    expect(find.text('BM-ORD-2026-QA'), findsOneWidget);
  });
  testWidgets('Annulation : dialogue et état relu du serveur', (tester) async {
    final repo = FakeCommerce();
    await screen(
      tester,
      const TransactionScreen('reservations', '12'),
      overrides: [
        commerceRepositoryProvider.overrideWithValue(repo),
        vehiclesProvider.overrideWithValue(FakeVehicles()),
      ],
    );
    await tester.pumpAndSettle();
    final cancel = find.widgetWithText(
      OutlinedButton,
      'Annuler la réservation',
    );
    await tester.scrollUntilVisible(
      cancel,
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(cancel);
    await tester.pumpAndSettle();
    expect(find.text('Annuler la réservation ?'), findsOneWidget);
    await tester.tap(
      find.widgetWithText(FilledButton, 'Annuler la réservation'),
    );
    await tester.pumpAndSettle();
    expect(repo.cancellations, 1);
    expect(find.text('Annulée'), findsOneWidget);
  });
  testWidgets('Liste reçus : données snapshots et actions natives', (
    tester,
  ) async {
    await screen(
      tester,
      const ReceiptsScreen(),
      overrides: [receiptRepositoryProvider.overrideWithValue(FakeReceipts())],
    );
    await tester.pumpAndSettle();
    expect(find.text('BM-RCP-2026-QA'), findsOneWidget);
    expect(find.text('Vendeur historique'), findsOneWidget);
    expect(find.text('Télécharger le PDF'), findsOneWidget);
    expect(find.text('Partager le reçu'), findsOneWidget);
  });
  testWidgets(
    'Reçu natif : coordonnées historiques, montant exact et mention DEMO',
    (tester) async {
      await screen(
        tester,
        const ReceiptDetailScreen('BM-RCP-2026-QA'),
        overrides: [
          receiptRepositoryProvider.overrideWithValue(FakeReceipts()),
        ],
      );
      await tester.pumpAndSettle();
      expect(find.text('Client historique'), findsOneWidget);
      expect(find.text('Peugeot historique'), findsOneWidget);
      expect(find.text('Reçu de démonstration'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('Total'),
        300,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('1 800,50 €'), findsWidgets);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('Reverb simulé : SOLD actualisé et CTA désactivé', (
    tester,
  ) async {
    final transport = FakeRealtime(), vehicles = FakeVehicles();
    final auth = FakeAuth(ApiClient(), MemoryStore());
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authProvider.overrideWith(
            (ref) => AuthController(auth)..setUser(testUser),
          ),
          vehiclesProvider.overrideWithValue(vehicles),
          realtimeProvider.overrideWithValue(transport),
        ],
        child: MaterialApp(
          theme: appTheme(),
          scaffoldMessengerKey: appMessengerKey,
          home: const RealtimeGate(child: VehicleScreen('toyota-rav4')),
        ),
      ),
    );
    await tester.pumpAndSettle();
    vehicles.items = [sample(sold: true)];
    transport.controller.add(
      const DomainSignal('VehicleStatusChanged', {'vehicle_id': '1'}),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 110));
    await tester.pumpAndSettle();
    expect(find.text('Vendu'), findsWidgets);
    final sold = find.widgetWithText(FilledButton, 'Vendu');
    expect(tester.widget<FilledButton>(sold).onPressed, isNull);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
    transport.dispose();
  });
  testWidgets('Reverb simulé : réservation confirmée et compte relu', (
    tester,
  ) async {
    final transport = FakeRealtime(), repo = FakeCommerce();
    final auth = FakeAuth(ApiClient(), MemoryStore());
    var statsReads = 0;
    final container = ProviderContainer(
      overrides: [
        authProvider.overrideWith(
          (ref) => AuthController(auth)..setUser(testUser),
        ),
        commerceRepositoryProvider.overrideWithValue(repo),
        vehiclesProvider.overrideWithValue(FakeVehicles()),
        realtimeProvider.overrideWithValue(transport),
        accountProvider.overrideWith((ref) async {
          statsReads++;
          return {};
        }),
      ],
    );
    addTearDown(container.dispose);
    final stats = container.listen(accountProvider, (_, _) {});
    addTearDown(stats.close);
    await container.read(accountProvider.future);
    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp(
          theme: appTheme(),
          scaffoldMessengerKey: appMessengerKey,
          home: const RealtimeGate(
            child: TransactionScreen('reservations', '12'),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    repo.row = recordJson(status: 'confirmed');
    transport.controller.add(
      const DomainSignal('ReservationConfirmed', {
        'id': '12',
        'status': 'confirmed',
      }),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 110));
    await tester.pumpAndSettle();
    expect(find.text('Confirmée'), findsOneWidget);
    expect(find.text('Votre réservation a été confirmée.'), findsOneWidget);
    expect(statsReads, 2);
    await tester.pumpWidget(const SizedBox());
    transport.dispose();
  });
}
