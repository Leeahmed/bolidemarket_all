import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/models.dart';
import '../../core/api.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../shared/widgets.dart';
import '../marketplace/query.dart';

final similarProvider = FutureProvider.autoDispose
    .family<List<Vehicle>, String>((ref, slug) async {
      final vehicle = await ref.watch(vehicleProvider(slug).future);
      final result = await ref
          .watch(vehiclesProvider)
          .search(
            SearchQuery(
              values: {
                'category': object(vehicle.json['category'])['slug'],
                'country_code': object(
                  object(vehicle.json['location'])['country'],
                )['code'],
                'status': 'available',
              },
            ),
          );
      return result.items.where((v) => v.id != vehicle.id).take(3).toList();
    });

class VehicleScreen extends ConsumerStatefulWidget {
  const VehicleScreen(this.slug, {super.key, this.preview, this.heroTag});
  final String slug;
  final Vehicle? preview;
  final Object? heroTag;
  @override
  ConsumerState<VehicleScreen> createState() => _VehicleScreenState();
}

class _VehicleScreenState extends ConsumerState<VehicleScreen> {
  int photo = 0;
  @override
  Widget build(BuildContext context) {
    final data = ref.watch(vehicleProvider(widget.slug));
    ref.listen(vehicleProvider(widget.slug), (previous, next) {
      final old = previous?.valueOrNull, current = next.valueOrNull;
      if (next.isLoading || current == null || old == null) return;
      final message = !old.sold && current.sold
          ? 'Ce véhicule vient d’être vendu.'
          : old.price('sale')?.amount != current.price('sale')?.amount ||
                old.price('rental')?.amount != current.price('rental')?.amount
          ? 'Le prix de ce véhicule a été modifié.'
          : null;
      if (message != null) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(message)));
      }
    });
    final view = data.isLoading && widget.preview != null
        ? AsyncData(widget.preview!)
        : data;
    final vehicle = view.valueOrNull;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Votre prochain bolide'),
        leading: IconButton(
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back),
          onPressed: () =>
              context.canPop() ? context.pop() : context.go('/marketplace'),
        ),
      ),
      bottomNavigationBar: vehicle == null
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    for (final offer in [
                      if (vehicle.sale) 'sale',
                      if (vehicle.rental) 'rental',
                    ])
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 4),
                          child: FilledButton(
                            onPressed:
                                vehicle.json['inventory_status'] !=
                                        'available' ||
                                    data.isLoading
                                ? null
                                : () => context.push(
                                    '/vehicle/${Uri.encodeComponent(widget.slug)}/${offer == 'sale' ? 'buy' : 'reserve'}',
                                  ),
                            child: Text(
                              vehicle.sold
                                  ? 'Vendu'
                                  : offer == 'sale'
                                  ? 'Acheter ce véhicule'
                                  : 'Réserver ce véhicule',
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
      body: view.when(
        loading: () =>
            const Padding(padding: EdgeInsets.all(24), child: LoadingCards()),
        error: (e, _) => Column(
          children: [
            ErrorPanel(
              ApiFailure.from(e).status == 404
                  ? const ApiFailure('Cette annonce n’est plus disponible.')
                  : e,
              () => ref.invalidate(vehicleProvider(widget.slug)),
            ),
            TextButton(
              onPressed: () => context.go('/marketplace'),
              child: const Text('Retour au marché'),
            ),
          ],
        ),
        data: (v) => ListView(
          children: [
            SizedBox(
              height: 260,
              child: Stack(
                children: [
                  HeroMode(
                    enabled: !MediaQuery.disableAnimationsOf(context),
                    child: Hero(
                      tag: widget.heroTag ?? 'vehicle:${widget.slug}',
                      child: PageView(
                        onPageChanged: (i) => setState(() => photo = i),
                        children: v.images.isEmpty
                            ? [
                                SafePhoto(
                                  '',
                                  asset: demoPhoto(
                                    v,
                                    object(v.json['primary_image']),
                                  ),
                                ),
                              ]
                            : v.images
                                  .map(
                                    (image) => InkWell(
                                      onTap: () => showDialog<void>(
                                        context: context,
                                        builder: (_) => Dialog(
                                          child: SizedBox(
                                            height: 400,
                                            child: InteractiveViewer(
                                              child: SafePhoto(
                                                text(image['url']),
                                                asset: demoPhoto(v, image),
                                                fit: BoxFit.contain,
                                              ),
                                            ),
                                          ),
                                        ),
                                      ),
                                      child: SafePhoto(
                                        text(image['url']),
                                        asset: demoPhoto(v, image),
                                      ),
                                    ),
                                  )
                                  .toList(),
                      ),
                    ),
                  ),
                  Positioned(
                    right: 16,
                    bottom: 16,
                    child: Chip(
                      label: Text(
                        '${photo + 1} / ${v.images.isEmpty ? 1 : v.images.length}',
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(v.title, style: AppTypography.title),
                  Wrap(
                    spacing: 8,
                    children: [
                      Chip(
                        label: Text(label(text(v.json['inventory_status']))),
                      ),
                      if (v.sale) const Chip(label: Text('À vendre')),
                      if (v.rental) const Chip(label: Text('À louer')),
                      if (v.json['is_certified'] == true)
                        const Chip(label: Text('Certifié')),
                    ],
                  ),
                  if (v.sale)
                    Text(v.price('sale')!.format(), style: AppTypography.price),
                  if (v.rental)
                    Text(
                      '${v.price('rental')!.format()} / jour',
                      style: AppTypography.price,
                    ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      const Icon(
                        Icons.location_on_outlined,
                        size: 16,
                        color: AppColors.muted,
                      ),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          '${v.location}${v.json['distance_km'] == null ? '' : ' · ${v.json['distance_km']} km'}',
                          style: const TextStyle(
                            color: AppColors.muted,
                            fontSize: 12,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SectionTitle('Caractéristiques'),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final key in [
                        'year',
                        'mileage_km',
                        'fuel',
                        'transmission',
                        'condition',
                        'engine',
                        'horsepower',
                        'doors',
                        'seats',
                        'color',
                      ])
                        if (v.json[key] != null)
                          Chip(
                            label: Text(
                              '${const {'year': 'Année', 'mileage_km': 'Kilométrage', 'fuel': 'Carburant', 'transmission': 'Transmission', 'condition': 'Condition', 'engine': 'Moteur', 'horsepower': 'Puissance', 'doors': 'Portes', 'seats': 'Places', 'color': 'Couleur'}[key]} : ${label(text(v.json[key]))}',
                            ),
                          ),
                    ],
                  ),
                  const SectionTitle('Description'),
                  Text(
                    text(v.json['description']).isEmpty
                        ? 'Description non renseignée.'
                        : text(v.json['description']),
                  ),
                  const SectionTitle('Équipements'),
                  Wrap(
                    spacing: 8,
                    children: (v.json['features'] as List? ?? [])
                        .map((f) => Chip(label: Text(text(object(f)['label']))))
                        .toList(),
                  ),
                  const SectionTitle('Le professionnel'),
                  Card(
                    child: ListTile(
                      title: Text(v.shopName),
                      subtitle: const Text('Voir la boutique'),
                      trailing: const Icon(Icons.arrow_forward),
                      onTap: () => context.push('/shop/${v.shopSlug}'),
                    ),
                  ),
                  const SectionTitle('Véhicules similaires'),
                  ref
                      .watch(similarProvider(widget.slug))
                      .when(
                        data: (rows) => rows.isEmpty
                            ? const Text('Aucune annonce similaire.')
                            : VehicleGrid(rows),
                        error: (e, _) => ErrorPanel(
                          e,
                          () => ref.invalidate(similarProvider(widget.slug)),
                        ),
                        loading: () => const LoadingCards(),
                      ),
                  if (v.demo) const DemoNotice(),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
