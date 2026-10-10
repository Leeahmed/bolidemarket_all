import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'providers.dart';
import 'models.dart';
import '../features/dev_preview/config.dart';
import '../features/dev_preview/gallery.dart';
import '../features/auth/auth_screen.dart';
import '../features/splash/splash_screen.dart';
import '../features/onboarding/onboarding_screen.dart';
import '../features/home/home_screen.dart';
import '../features/marketplace/marketplace_screen.dart';
import '../features/vehicle/vehicle_screen.dart';
import '../features/shop/shop_screen.dart';
import '../features/account/account_screen.dart';
import '../features/profile/profile_screen.dart';
import '../features/commerce/workflow_screen.dart';
import '../features/commerce/history_screen.dart';
import '../features/commerce/receipts_screen.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = ValueNotifier(0);
  ref.listen(authProvider, (_, _) => refresh.value++);
  final router = GoRouter(
    initialLocation: visualDemoEnabled ? '/dev-preview' : '/splash',
    refreshListenable: refresh,
    redirect: (_, state) {
      final protected =
          state.uri.path.startsWith('/account') ||
          state.uri.path == '/profile' ||
          (visualDemoEnabled &&
              (state.uri.path.startsWith('/dev-preview/rental/') ||
                  state.uri.path.startsWith('/dev-preview/sale/'))) ||
          state.uri.path.startsWith('/reservation-confirmation/') ||
          state.uri.path.startsWith('/order-confirmation/') ||
          (state.uri.path.startsWith('/vehicle/') &&
              (state.uri.path.endsWith('/reserve') ||
                  state.uri.path.endsWith('/buy')));
      final auth = ref.read(authProvider);
      if (protected && auth.valueOrNull == null) {
        return '/login?redirect=${Uri.encodeComponent(state.uri.toString())}';
      }
      return null;
    },
    routes: [
      if (visualDemoEnabled) ...[
        GoRoute(
          path: '/dev-preview',
          builder: (_, _) => const DevPreviewGallery(),
        ),
        GoRoute(
          path: '/dev-preview/splash',
          builder: (_, _) => const SplashScreen(startAutomatically: false),
        ),
        GoRoute(
          path: '/dev-preview/filters',
          builder: (_, _) => const PreviewFiltersScreen(),
        ),
        GoRoute(
          path: '/dev-preview/:kind/:step',
          builder: (_, state) => PreviewWorkflowScreen(
            rental: state.pathParameters['kind'] == 'rental',
            step:
                int.tryParse(state.pathParameters['step'] ?? '')?.clamp(0, 2) ??
                0,
          ),
        ),
      ],
      GoRoute(path: '/', redirect: (_, _) => '/home'),
      GoRoute(path: '/splash', builder: (_, _) => const SplashScreen()),
      GoRoute(path: '/onboarding', builder: (_, _) => const OnboardingScreen()),
      GoRoute(
        path: '/login',
        builder: (_, state) =>
            AuthScreen(redirect: state.uri.queryParameters['redirect']),
      ),
      GoRoute(
        path: '/register',
        builder: (_, state) => AuthScreen(
          register: true,
          redirect: state.uri.queryParameters['redirect'],
        ),
      ),
      GoRoute(
        path: '/vehicle/:slug',
        builder: (_, state) {
          final extra = state.extra is Map ? state.extra as Map : {};
          return VehicleScreen(
            state.pathParameters['slug']!,
            preview: extra['vehicle'] as Vehicle?,
            heroTag: extra['hero'],
          );
        },
      ),
      GoRoute(
        path: '/shop/:slug',
        builder: (_, state) => ShopScreen(state.pathParameters['slug']!),
      ),
      GoRoute(path: '/profile', builder: (_, _) => const ProfileScreen()),
      for (final action in ['reserve', 'buy'])
        GoRoute(
          path: '/vehicle/:slug/$action',
          builder: (_, state) => CommerceWorkflowScreen(
            state.pathParameters['slug']!,
            rental: action == 'reserve',
          ),
        ),
      for (final kind in ['reservations', 'orders']) ...[
        GoRoute(
          path: '/account/$kind/:identifier',
          builder: (_, state) =>
              TransactionScreen(kind, state.pathParameters['identifier']!),
        ),
        GoRoute(
          path:
              '/${kind == 'reservations' ? 'reservation' : 'order'}-confirmation/:reference',
          builder: (_, state) => TransactionScreen(
            kind,
            state.pathParameters['reference']!,
            confirmation: true,
          ),
        ),
      ],
      GoRoute(
        path: '/account/receipts/:reference',
        builder: (_, state) =>
            ReceiptDetailScreen(state.pathParameters['reference']!),
      ),
      ShellRoute(
        builder: (_, state, child) => AppShell(state.uri.path, child),
        routes: [
          GoRoute(path: '/home', builder: (_, _) => const HomeScreen()),
          GoRoute(
            path: '/marketplace',
            builder: (_, state) => MarketplaceScreen(
              key: ValueKey(state.uri.toString()),
              initial: state.uri.queryParameters,
            ),
          ),
          GoRoute(path: '/account', builder: (_, _) => const AccountScreen()),
          GoRoute(
            path: '/account/favorites',
            builder: (_, _) => const FavoritesScreen(),
          ),
          GoRoute(
            path: '/account/receipts',
            builder: (_, _) => const ReceiptsScreen(),
          ),
          for (final kind in ['reservations', 'orders'])
            GoRoute(
              path: '/account/$kind',
              builder: (_, _) => HistoryScreen(kind),
            ),
        ],
      ),
    ],
    errorBuilder: (context, _) => Scaffold(
      body: Center(
        child: FilledButton(
          onPressed: () => context.go('/home'),
          child: const Text('Retour à l’accueil'),
        ),
      ),
    ),
  );
  ref.onDispose(() {
    router.dispose();
    refresh.dispose();
  });
  return router;
});

class AppShell extends StatelessWidget {
  const AppShell(this.path, this.child, {super.key});
  final String path;
  final Widget child;
  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(child: child),
    bottomNavigationBar: NavigationBar(
      selectedIndex: path == '/home'
          ? 0
          : path == '/marketplace'
          ? 1
          : path == '/account/favorites'
          ? 2
          : 3,
      onDestinationSelected: (index) => context.go(
        ['/home', '/marketplace', '/account/favorites', '/account'][index],
      ),
      destinations: const [
        NavigationDestination(
          icon: Icon(Icons.home_outlined),
          selectedIcon: Icon(Icons.home),
          label: 'Accueil',
        ),
        NavigationDestination(icon: Icon(Icons.search), label: 'Explorer'),
        NavigationDestination(
          icon: Icon(Icons.favorite_border),
          label: 'Favoris',
        ),
        NavigationDestination(
          icon: Icon(Icons.person_outline),
          label: 'Compte',
        ),
      ],
    ),
  );
}
