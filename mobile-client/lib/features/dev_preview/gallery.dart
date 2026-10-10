import 'dart:math';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/providers.dart';
import '../../core/router.dart';
import '../../core/theme.dart';
import '../commerce/models.dart';
import '../commerce/workflow_screen.dart';
import '../marketplace/filter_sheet.dart';
import '../marketplace/marketplace_screen.dart';
import 'providers.dart';

const previewGroups = <String, List<(String, String)>>{
  'AUTH': [
    ('Splash', '/dev-preview/splash'),
    ('Onboarding', '/onboarding'),
    ('Login', '/login'),
    ('Register', '/register'),
  ],
  'MARKETPLACE': [
    ('Home', '/home'),
    ('Marketplace', '/marketplace'),
    ('Recherche', '/marketplace?q=Toyota'),
    ('Filtres', '/dev-preview/filters'),
    ('Vehicle SALE', '/vehicle/toyota-rav4'),
    ('Vehicle RENTAL', '/vehicle/peugeot-208'),
    ('Vehicle SOLD', '/vehicle/kia-sportage'),
    ('Shop Detail', '/shop/abidjan-prestige-motors'),
  ],
  'TRANSACTIONS': [
    ('Reservation dates', '/vehicle/peugeot-208/reserve'),
    ('Reservation summary', '/dev-preview/rental/1'),
    ('Demo payment — location', '/dev-preview/rental/2'),
    ('Reservation confirmation', '@reservation'),
    ('Purchase summary', '/vehicle/toyota-rav4/buy'),
    ('Remise du véhicule', '/dev-preview/sale/1'),
    ('Demo payment — vente', '/dev-preview/sale/2'),
    ('Order confirmation', '@order'),
    ('Receipt detail', '@receipt'),
  ],
  'ACCOUNT': [
    ('Account Home', '/account'),
    ('Favoris', '/account/favorites'),
    ('Profile', '/profile'),
    ('Reservations', '/account/reservations'),
    ('Orders', '/account/orders'),
    ('Receipts', '/account/receipts'),
  ],
};

class DevPreviewGallery extends ConsumerWidget {
  const DevPreviewGallery({super.key});
  Future<void> session(WidgetRef ref, bool connected) async {
    final auth = ref.read(authProvider.notifier);
    if (connected) {
      await auth.login('djak@bolidemarket.demo', 'local-preview');
    } else {
      await auth.logout();
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(demoStateProvider);
    final connected = ref.watch(authProvider).valueOrNull != null;
    String route(String target) => switch (target) {
      '@reservation' =>
        '/reservation-confirmation/${data.reservations.first['reference']}',
      '@order' =>
        '/order-confirmation/${data.orders.firstWhere((r) => r['kind'] == 'sale' && r['status'] == 'confirmed')['reference']}',
      '@receipt' => '/account/receipts/${data.receipts.first['reference']}',
      _ => target,
    };
    return Scaffold(
      appBar: AppBar(title: const Text('Mobile Visual QA')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          const Text(
            'DEMO · DEBUG / DEVELOPMENT',
            style: AppTypography.heading,
          ),
          const SizedBox(height: 8),
          const Text(
            'Données locales. Connexions, confirmations et paiements simulés. Aucun appel Laravel.',
          ),
          const SizedBox(height: 16),
          SegmentedButton<bool>(
            segments: const [
              ButtonSegment(value: false, label: Text('Guest')),
              ButtonSegment(value: true, label: Text('Client connecté')),
            ],
            selected: {connected},
            onSelectionChanged: (v) => session(ref, v.single),
          ),
          const SizedBox(height: 8),
          Text(
            connected
                ? 'Djak Kouadou · Cocody, Abidjan · XOF'
                : 'Invité · accès privés via Login',
          ),
          for (final group in previewGroups.entries) ...[
            Padding(
              padding: const EdgeInsets.only(top: 24, bottom: 8),
              child: Text(group.key, style: AppTypography.heading),
            ),
            for (final preview in group.value)
              Card(
                child: ListTile(
                  title: Text(preview.$1),
                  trailing: const Icon(Icons.arrow_forward, size: 20),
                  onTap: () => context.go(route(preview.$2)),
                ),
              ),
          ],
        ],
      ),
    );
  }
}

/// Root overlay remains visible above dialogs/sheets, and preserves the real router.
class VisualDemoFrame extends ConsumerWidget {
  const VisualDemoFrame({super.key, required this.child});
  final Widget child;
  @override
  Widget build(BuildContext context, WidgetRef ref) => LayoutBuilder(
    builder: (context, limits) {
      final width = kIsWeb
          ? min(limits.maxWidth, limits.maxWidth > 600 ? 390.0 : 430.0)
          : limits.maxWidth;
      final media = MediaQuery.of(
        context,
      ).copyWith(size: Size(width, limits.maxHeight));
      return ColoredBox(
        color: AppColors.carbon,
        child: Align(
          alignment: Alignment.topCenter,
          child: SizedBox(
            width: width,
            height: limits.maxHeight,
            child: MediaQuery(
              data: media,
              child: Stack(
                children: [
                  Positioned.fill(child: child),
                  Positioned(
                    right: 8,
                    bottom: media.padding.bottom + 88,
                    child: Material(
                      color: AppColors.carbon.withValues(alpha: .92),
                      borderRadius: BorderRadius.circular(24),
                      child: IconButton(
                        icon: const Icon(
                          Icons.grid_view,
                          semanticLabel: 'Galerie DEBUG — aperçu local',
                          size: 18,
                          color: AppColors.ivory,
                        ),
                        onPressed: () =>
                            ref.read(routerProvider).go('/dev-preview'),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
    },
  );
}

class PreviewFiltersScreen extends ConsumerStatefulWidget {
  const PreviewFiltersScreen({super.key});
  @override
  ConsumerState<PreviewFiltersScreen> createState() => _PreviewFiltersState();
}

class _PreviewFiltersState extends ConsumerState<PreviewFiltersScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final query = await filterSheet(context, ref.read(searchQueryProvider));
      if (!mounted) return;
      if (query != null) ref.read(searchQueryProvider.notifier).state = query;
      context.go('/marketplace', extra: query);
    });
  }

  @override
  Widget build(BuildContext context) =>
      const AppShell('/marketplace', MarketplaceScreen());
}

class PreviewWorkflowScreen extends ConsumerWidget {
  const PreviewWorkflowScreen({
    super.key,
    required this.rental,
    required this.step,
  });
  final bool rental;
  final int step;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final today = shopToday('Africa/Abidjan');
    final start = today.add(const Duration(days: 1));
    final end = today.add(const Duration(days: 4));
    final data = ref.read(demoStateProvider);
    return CommerceWorkflowScreen(
      rental ? 'peugeot-208' : 'toyota-rav4',
      rental: rental,
      initialDraft: {
        'step': step,
        'start': start,
        'end': end,
        if (rental) 'quote': data.makeQuote('2', start, end),
        'meeting': start,
        'hour': const TimeOfDay(hour: 12, minute: 0),
      },
    );
  }
}
