import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../shared/widgets.dart';
import '../location/location_sheet.dart';
import 'filter_sheet.dart';

class MarketplaceScreen extends ConsumerStatefulWidget {
  const MarketplaceScreen({super.key, this.initial = const {}});
  final Json initial;
  @override
  ConsumerState<MarketplaceScreen> createState() => _MarketplaceScreenState();
}

class _MarketplaceScreenState extends ConsumerState<MarketplaceScreen> {
  late final search = TextEditingController(text: text(widget.initial['q']));
  Timer? debounce;
  bool more = false;
  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      if (mounted) {
        ref.read(searchQueryProvider.notifier).state = ref
            .read(searchQueryProvider)
            .copy(widget.initial);
      }
    });
  }

  @override
  void dispose() {
    debounce?.cancel();
    search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final query = ref.watch(searchQueryProvider);
    final results = ref.watch(catalogueProvider);
    return RefreshIndicator(
      onRefresh: () => ref.read(catalogueProvider.notifier).reload(),
      child: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          const Text('Explorer le marché', style: AppTypography.title),
          const SizedBox(height: 20),
          TextField(
            controller: search,
            maxLength: 120,
            decoration: const InputDecoration(
              hintText: 'Marque, modèle, ville ou professionnel',
              prefixIcon: Icon(Icons.search),
              counterText: '',
            ),
            onChanged: (v) {
              debounce?.cancel();
              debounce = Timer(const Duration(milliseconds: 400), () {
                if (mounted) {
                  ref.read(searchQueryProvider.notifier).state = ref
                      .read(searchQueryProvider)
                      .copy({'q': v.trim()});
                }
              });
            },
          ),
          Wrap(
            spacing: 8,
            children: [
              OutlinedButton.icon(
                onPressed: () async {
                  final value = await filterSheet(context, query);
                  if (value != null && mounted) {
                    ref.read(searchQueryProvider.notifier).state = value;
                  }
                },
                icon: const Icon(Icons.tune),
                label: const Text('Filtres'),
              ),
              OutlinedButton.icon(
                onPressed: () async {
                  final sort = await showModalBottomSheet<String>(
                    context: context,
                    builder: (_) => SafeArea(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          for (final option in [
                            ('distance', 'Plus proches'),
                            ('newest', 'Plus récents'),
                            ('price_asc', 'Prix croissant'),
                            ('price_desc', 'Prix décroissant'),
                            ('year_desc', 'Année récente'),
                            ('mileage_asc', 'Kilométrage'),
                          ])
                            ListTile(
                              title: Text(option.$2),
                              enabled:
                                  option.$1 != 'distance' ||
                                  query.values['latitude'] != null,
                              onTap: () => Navigator.pop(context, option.$1),
                            ),
                        ],
                      ),
                    ),
                  );
                  if (sort != null && context.mounted) {
                    if (sort.startsWith('price') &&
                        (query.values['listing_type'] == null ||
                            query.values['currency'] == null)) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text(
                            'Choisissez une offre et une devise dans les filtres.',
                          ),
                        ),
                      );
                      return;
                    }
                    ref.read(searchQueryProvider.notifier).state = query.copy({
                      'sort': sort,
                    });
                  }
                },
                icon: const Icon(Icons.sort),
                label: const Text('Tri'),
              ),
              TextButton.icon(
                onPressed: () => chooseLocation(context),
                icon: const Icon(Icons.location_on_outlined),
                label: const Text('Localisation'),
              ),
            ],
          ),
          const SizedBox(height: 16),
          results.when(
            loading: () => const LoadingCards(),
            error: (e, _) => ErrorPanel(
              e,
              () => ref.read(catalogueProvider.notifier).reload(),
            ),
            data: (page) => Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  '${page.total} résultats',
                  style: const TextStyle(color: AppColors.muted),
                ),
                const SizedBox(height: 16),
                if (page.items.isEmpty)
                  const Padding(
                    padding: EdgeInsets.all(24),
                    child: Text(
                      'Aucun véhicule ne correspond à ces critères. Ajustez les filtres.',
                    ),
                  )
                else
                  VehicleGrid(
                    page.items,
                    offer: text(query.values['listing_type']),
                  ),
                if (page.hasMore)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 20),
                    child: FilledButton(
                      onPressed: more
                          ? null
                          : () async {
                              setState(() => more = true);
                              try {
                                await ref
                                    .read(catalogueProvider.notifier)
                                    .more();
                              } catch (e) {
                                if (context.mounted) showError(context, e);
                              } finally {
                                if (mounted) setState(() => more = false);
                              }
                            },
                      child: Text(more ? 'Chargement…' : 'Charger davantage'),
                    ),
                  ),
                if (page.items.any((v) => v.demo)) const DemoNotice(),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
