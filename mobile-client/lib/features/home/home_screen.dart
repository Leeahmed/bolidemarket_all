import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/providers.dart';
import '../../core/models.dart';
import '../../core/theme.dart';
import '../../shared/widgets.dart';
import '../location/location_sheet.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});
  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  final search = TextEditingController();
  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  void explore([String? offer]) => context.go(
    Uri(
      path: '/marketplace',
      queryParameters: {
        if (search.text.trim().isNotEmpty) 'q': search.text.trim(),
        'listing_type': ?offer,
      },
    ).toString(),
  );
  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authProvider).valueOrNull;
    final data = ref.watch(homeProvider);
    final nearby = ref.watch(contextProvider);
    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(homeProvider);
        await ref.read(homeProvider.future);
      },
      child: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Row(
            children: [
              const Expanded(
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: BrandLogo(width: 205),
                ),
              ),
              IconButton(
                tooltip: 'Localisation',
                onPressed: () => chooseLocation(context),
                icon: const Icon(Icons.location_on_outlined),
              ),
            ],
          ),
          const SizedBox(height: 24),
          Text(
            'Bonjour${user == null ? '' : ', ${user.firstName}'} 👋',
            style: AppTypography.heading,
          ),
          TextButton.icon(
            onPressed: () => chooseLocation(context),
            icon: const Icon(Icons.location_on_outlined),
            label: Text(
              ref.watch(locationLabelProvider) ??
                  (nearby['latitude'] != null
                      ? 'Autour de ma position'
                      : user?.location.isNotEmpty == true
                      ? user!.location
                      : 'Choisir ma localisation'),
            ),
          ),
          const SizedBox(height: 16),
          const Text(
            'Quel sera votre prochain bolide ?',
            style: AppTypography.title,
          ),
          const SizedBox(height: 20),
          TextField(
            controller: search,
            maxLength: 120,
            textInputAction: TextInputAction.search,
            onSubmitted: (_) => explore(),
            decoration: const InputDecoration(
              hintText: 'Marque ou modèle',
              counterText: '',
              prefixIcon: Icon(Icons.search),
            ),
          ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: () => explore('sale'),
            child: const Text('Acheter'),
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: () => explore('rental'),
            child: const Text(
              'Louer',
              style: TextStyle(color: AppColors.carbon),
            ),
          ),
          data.when(
            loading: () => const Padding(
              padding: EdgeInsets.only(top: 24),
              child: LoadingCards(),
            ),
            error: (e, _) => ErrorPanel(e, () => ref.invalidate(homeProvider)),
            data: (home) => Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SectionTitle(
                  nearby['latitude'] != null || nearby['city_id'] != null
                      ? 'Près de vous'
                      : 'À découvrir',
                  onTap: explore,
                ),
                if (home.nearby.items.isEmpty)
                  const Text(
                    'Aucune annonce dans ce marché. Explorez les autres pays.',
                  )
                else
                  VehicleGrid(home.nearby.items.take(3).toList()),
                SectionTitle(
                  'Nouvelles annonces',
                  onTap: () => context.go('/marketplace?sort=newest'),
                ),
                if (home.newest.items.isEmpty)
                  const Text('Aucune nouvelle annonce.')
                else
                  VehicleGrid(home.newest.items.take(3).toList()),
                const SectionTitle('Professionnels à découvrir'),
                for (final shop in home.shops)
                  Card(
                    color: Colors.white,
                    child: ListTile(
                      leading: SizedBox(
                        width: 50,
                        height: 50,
                        child: SafePhoto(text(shop['logo_url'])),
                      ),
                      title: Text(text(shop['name'])),
                      subtitle: Text(place(object(shop['location']))),
                      trailing: const Icon(Icons.arrow_forward),
                      onTap: () => context.push('/shop/${shop['slug']}'),
                    ),
                  ),
                if (home.shops.isEmpty)
                  const Text('Aucune boutique disponible.'),
                if (home.nearby.items.any((v) => v.demo) ||
                    home.shops.any((s) => s['is_demo'] == true))
                  const DemoNotice(),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
