import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../commerce/providers.dart';
import '../realtime/gate.dart';
import 'config.dart';
import 'fixtures.dart';
import 'repositories.dart';

final demoStateProvider = Provider((_) => DemoState());

List<Override> visualDemoOverrides() {
  if (!visualDemoEnabled) {
    throw StateError('Aperçu réservé au debug/development.');
  }
  return demoRepositoryOverrides();
}

/// Also used in isolated tests, never installed in the app unless the guard passes.
List<Override> demoRepositoryOverrides() => [
  apiProvider.overrideWith(
    (_) => DemoApiClient()..token = 'visual-local-session',
  ),
  sessionStoreProvider.overrideWith((_) => DemoSessionStore()),
  authRepositoryProvider.overrideWith(
    (ref) => DemoAuthRepository(
      ref.watch(apiProvider),
      ref.watch(sessionStoreProvider),
      ref.watch(demoStateProvider),
    ),
  ),
  vehiclesProvider.overrideWith(
    (ref) => DemoVehicleRepository(
      ref.watch(apiProvider),
      ref.watch(demoStateProvider),
    ),
  ),
  shopsProvider.overrideWith(
    (ref) => DemoShopRepository(
      ref.watch(apiProvider),
      ref.watch(demoStateProvider),
    ),
  ),
  favoriteRepositoryProvider.overrideWith(
    (ref) => DemoFavoriteRepository(
      ref.watch(apiProvider),
      ref.watch(demoStateProvider),
    ),
  ),
  accountRepositoryProvider.overrideWith(
    (ref) => DemoAccountRepository(
      ref.watch(apiProvider),
      ref.watch(demoStateProvider),
    ),
  ),
  locationRepositoryProvider.overrideWith((_) => DemoLocationRepository()),
  commerceRepositoryProvider.overrideWith(
    (ref) => DemoCommerceRepository(
      ref.watch(apiProvider),
      ref.watch(demoStateProvider),
    ),
  ),
  receiptRepositoryProvider.overrideWith(
    (ref) => DemoReceiptRepository(
      ref.watch(apiProvider),
      ref.watch(demoStateProvider),
    ),
  ),
  realtimeProvider.overrideWith((_) => DemoRealtimeTransport()),
  authProvider.overrideWith(
    (ref) => AuthController(ref.watch(authRepositoryProvider))
      ..onboarding = false
      ..setUser(AppUser(copyDemo(ref.watch(demoStateProvider).user))),
  ),
];
