import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../shared/widgets.dart';
import '../marketplace/query.dart';

class ShopScreen extends ConsumerStatefulWidget {
  const ShopScreen(this.slug, {super.key});
  final String slug;
  @override
  ConsumerState<ShopScreen> createState() => _ShopScreenState();
}

class _ShopScreenState extends ConsumerState<ShopScreen> {
  int tab = 0;
  int generation = 0;
  bool more = false;
  AsyncValue<PageData<Vehicle>> vehicles = const AsyncLoading();
  @override
  void initState() {
    super.initState();
    Future.microtask(load);
  }

  SearchQuery get query => SearchQuery(
    values: {
      if (tab == 1) 'listing_type': 'sale',
      if (tab == 2) 'listing_type': 'rental',
      if (tab == 3) 'status': 'available',
    },
  );
  Future<void> load() async {
    final current = ++generation;
    setState(() => vehicles = const AsyncLoading());
    try {
      final result = await ref
          .read(vehiclesProvider)
          .search(query, shop: widget.slug);
      if (mounted && generation == current) {
        setState(() => vehicles = AsyncData(result));
      }
    } catch (e, st) {
      if (mounted && generation == current) {
        setState(() => vehicles = AsyncError(e, st));
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Boutique professionnelle'),
      leading: IconButton(
        tooltip: 'Retour',
        icon: const Icon(Icons.arrow_back),
        onPressed: () =>
            context.canPop() ? context.pop() : context.go('/marketplace'),
      ),
    ),
    body: ref
        .watch(shopProvider(widget.slug))
        .when(
          loading: () =>
              const Padding(padding: EdgeInsets.all(24), child: LoadingCards()),
          error: (e, _) =>
              ErrorPanel(e, () => ref.invalidate(shopProvider(widget.slug))),
          data: (shop) => ListView(
            children: [
              SizedBox(height: 190, child: SafePhoto(text(shop['cover_url']))),
              Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        SizedBox(
                          width: 64,
                          height: 64,
                          child: SafePhoto(text(shop['logo_url'])),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Text(
                            text(shop['name']),
                            style: AppTypography.heading,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Text(place(object(shop['location']))),
                    Text(
                      (shop['sale_vehicles_count'] as num? ?? 0) > 0 &&
                              (shop['rental_vehicles_count'] as num? ?? 0) > 0
                          ? 'Vente & Location'
                          : (shop['sale_vehicles_count'] as num? ?? 0) > 0
                          ? 'Vente'
                          : (shop['rental_vehicles_count'] as num? ?? 0) > 0
                          ? 'Location'
                          : 'Aucune annonce publique',
                    ),
                    const SizedBox(height: 12),
                    Text(text(shop['description'])),
                    const SizedBox(height: 16),
                    Wrap(
                      spacing: 8,
                      children: [
                        for (var i = 0; i < 4; i++)
                          ChoiceChip(
                            label: Text(
                              ['Tous', 'À vendre', 'À louer', 'Disponibles'][i],
                            ),
                            selected: tab == i,
                            onSelected: (_) {
                              setState(() => tab = i);
                              load();
                            },
                          ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    vehicles.when(
                      loading: () => const LoadingCards(),
                      error: (e, _) => ErrorPanel(e, load),
                      data: (page) => Column(
                        children: [
                          if (page.items.isEmpty)
                            const Text('Aucun véhicule dans cette sélection.')
                          else
                            VehicleGrid(
                              page.items,
                              offer: tab == 1
                                  ? 'sale'
                                  : tab == 2
                                  ? 'rental'
                                  : null,
                            ),
                          if (page.hasMore)
                            FilledButton(
                              onPressed: more
                                  ? null
                                  : () async {
                                      final current = generation;
                                      setState(() => more = true);
                                      try {
                                        final next = await ref
                                            .read(vehiclesProvider)
                                            .search(
                                              query,
                                              page: page.page + 1,
                                              shop: widget.slug,
                                            );
                                        if (mounted && generation == current) {
                                          setState(
                                            () => vehicles = AsyncData(
                                              PageData(
                                                [...page.items, ...next.items],
                                                next.page,
                                                next.lastPage,
                                                next.total,
                                              ),
                                            ),
                                          );
                                        }
                                      } catch (e) {
                                        if (context.mounted) {
                                          showError(context, e);
                                        }
                                      } finally {
                                        if (mounted) {
                                          setState(() => more = false);
                                        }
                                      }
                                    },
                              child: Text(
                                more ? 'Chargement…' : 'Charger davantage',
                              ),
                            ),
                        ],
                      ),
                    ),
                    if (shop['is_demo'] == true) const DemoNotice(),
                  ],
                ),
              ),
            ],
          ),
        ),
  );
}
