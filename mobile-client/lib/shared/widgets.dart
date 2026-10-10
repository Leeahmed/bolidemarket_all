import 'dart:convert';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../core/api.dart';
import '../core/models.dart';
import '../core/providers.dart';
import '../core/theme.dart';

class BrandLogo extends StatelessWidget {
  const BrandLogo({super.key, this.dark = false, this.width = 210});
  final bool dark;
  final double width;
  @override
  Widget build(BuildContext context) => Image.asset(
    dark ? 'assets/images/logo-light.webp' : 'assets/images/logo-dark.png',
    width: width,
    semanticLabel: 'BolideMarket — Achetez. Louez. Roulez.',
  );
}

class SafePhoto extends StatelessWidget {
  const SafePhoto(this.url, {super.key, this.asset, this.fit = BoxFit.cover});
  final String url;
  final String? asset;
  final BoxFit fit;
  @override
  Widget build(BuildContext context) {
    Widget placeholder() => Container(
      color: AppColors.graphite,
      alignment: Alignment.center,
      child: const Icon(
        Icons.directions_car_outlined,
        color: AppColors.ivory,
        size: 48,
      ),
    );
    if (asset != null || url.startsWith('assets/')) {
      return Image.asset(
        asset ?? url,
        fit: fit,
        errorBuilder: (_, _, _) => placeholder(),
      );
    }
    if (url.startsWith('data:image/')) {
      try {
        return Image.memory(
          base64Decode(url.split(',').last),
          fit: fit,
          errorBuilder: (_, _, _) => placeholder(),
        );
      } catch (_) {
        return placeholder();
      }
    }
    if (url.isEmpty || url.toLowerCase().endsWith('.svg')) return placeholder();
    return CachedNetworkImage(
      imageUrl: AppConfig.mediaUrl(url),
      fit: fit,
      placeholder: (_, _) => placeholder(),
      errorWidget: (_, _, _) => placeholder(),
    );
  }
}

class LoadingCards extends StatelessWidget {
  const LoadingCards({super.key});
  @override
  Widget build(BuildContext context) => Semantics(
    label: 'Chargement',
    child: SingleChildScrollView(
      child: Column(
        children: List.generate(
          3,
          (_) => Container(
            height: 200,
            margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(
              color: AppColors.border,
              borderRadius: BorderRadius.circular(AppRadius.card),
            ),
          ),
        ),
      ),
    ),
  );
}

class ErrorPanel extends StatelessWidget {
  const ErrorPanel(this.error, this.retry, {super.key});
  final Object error;
  final VoidCallback retry;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(24),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.cloud_off_outlined, size: 40),
        const SizedBox(height: 16),
        Text(ApiFailure.from(error).message, textAlign: TextAlign.center),
        const SizedBox(height: 16),
        FilledButton(onPressed: retry, child: const Text('Réessayer')),
      ],
    ),
  );
}

class DemoNotice extends StatelessWidget {
  const DemoNotice({super.key});
  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.symmetric(vertical: 20),
    child: Text(
      'Boutiques, véhicules, prix et données de démonstration.',
      style: TextStyle(fontSize: 12, color: AppColors.muted),
      textAlign: TextAlign.center,
    ),
  );
}

void showError(BuildContext context, Object error) =>
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          error is FormatException
              ? error.message
              : ApiFailure.from(error).message,
        ),
      ),
    );

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.title, {super.key, this.onTap});
  final String title;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 16),
    child: Row(
      children: [
        Expanded(child: Text(title, style: AppTypography.heading)),
        if (onTap != null)
          TextButton(
            onPressed: onTap,
            child: const Text(
              'Voir tout →',
              style: TextStyle(color: AppColors.carbon),
            ),
          ),
      ],
    ),
  );
}

class VehicleCard extends ConsumerStatefulWidget {
  const VehicleCard(this.vehicle, {super.key, this.offer});
  final Vehicle vehicle;
  final String? offer;
  @override
  ConsumerState<VehicleCard> createState() => _VehicleCardState();
}

class _VehicleCardState extends ConsumerState<VehicleCard> {
  bool busy = false;
  final Object heroTag = Object();
  @override
  Widget build(BuildContext context) {
    final vehicle = widget.vehicle;
    final offers = widget.offer == 'rental' && vehicle.rental
        ? ['rental']
        : widget.offer == 'sale' && vehicle.sale
        ? ['sale']
        : [if (vehicle.sale) 'sale', if (vehicle.rental) 'rental'];
    final favorite =
        ref
            .watch(favoritesProvider)
            .valueOrNull
            ?.any((v) => v.id == vehicle.id) ??
        false;
    final photo = object(vehicle.json['primary_image']);
    final asset = demoPhoto(vehicle, photo);
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(AppRadius.card),
        boxShadow: AppShadows.card,
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Stack(
            children: [
              AspectRatio(
                aspectRatio: 1.65,
                child: InkWell(
                  onTap: () => context.push(
                    '/vehicle/${vehicle.slug}',
                    extra: {'vehicle': vehicle, 'hero': heroTag},
                  ),
                  child: HeroMode(
                    enabled: !MediaQuery.disableAnimationsOf(context),
                    child: Hero(
                      tag: heroTag,
                      child: SafePhoto(text(photo['url']), asset: asset),
                    ),
                  ),
                ),
              ),
              Positioned(
                left: 10,
                top: 10,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.ivory,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    vehicle.sold
                        ? 'Vendu'
                        : offers
                              .map(
                                (o) => o == 'rental' ? 'À louer' : 'À vendre',
                              )
                              .join(' · '),
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                ),
              ),
              Positioned(
                right: 4,
                top: 4,
                child: IconButton.filledTonal(
                  tooltip: favorite
                      ? 'Retirer des favoris'
                      : 'Ajouter aux favoris',
                  onPressed: busy
                      ? null
                      : () async {
                          if (ref.read(authProvider).valueOrNull == null) {
                            final route = GoRouterState.of(
                              context,
                            ).uri.toString();
                            context.push(
                              '/login?redirect=${Uri.encodeComponent(route)}',
                            );
                            return;
                          }
                          setState(() => busy = true);
                          try {
                            await ref
                                .read(favoriteRepositoryProvider)
                                .set(vehicle.id, !favorite);
                            ref.invalidate(favoritesProvider);
                          } catch (e) {
                            if (context.mounted) showError(context, e);
                          } finally {
                            if (mounted) setState(() => busy = false);
                          }
                        },
                  icon: AnimatedSwitcher(
                    duration: MediaQuery.disableAnimationsOf(context)
                        ? Duration.zero
                        : const Duration(milliseconds: 160),
                    child: Icon(
                      favorite ? Icons.favorite : Icons.favorite_border,
                      key: ValueKey(favorite),
                      color: favorite ? AppColors.orange : AppColors.carbon,
                    ),
                  ),
                ),
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                InkWell(
                  onTap: () => context.push(
                    '/vehicle/${vehicle.slug}',
                    extra: {'vehicle': vehicle, 'hero': heroTag},
                  ),
                  child: Text(
                    vehicle.title,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 18,
                    ),
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  '${text(vehicle.json['year'])} · ${label(text(vehicle.json['transmission']))} · ${label(text(vehicle.json['fuel']))}',
                  style: const TextStyle(color: AppColors.muted, fontSize: 12),
                ),
                for (final offer in offers)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(
                      '${vehicle.price(offer)?.format() ?? 'Prix indisponible'}${offer == 'rental' ? ' / jour' : ''}',
                      style: AppTypography.price.copyWith(fontSize: 18),
                    ),
                  ),
                const SizedBox(height: 8),
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
                        '${vehicle.location}${vehicle.json['distance_km'] == null ? '' : ' · ${vehicle.json['distance_km']} km'}',
                        style: const TextStyle(
                          color: AppColors.muted,
                          fontSize: 12,
                        ),
                      ),
                    ),
                  ],
                ),
                TextButton(
                  onPressed: () => context.push('/shop/${vehicle.shopSlug}'),
                  child: Text(
                    vehicle.shopName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: AppColors.carbon),
                  ),
                ),
                if (asset != null)
                  const Text(
                    'Illustration de démonstration',
                    style: TextStyle(color: AppColors.muted, fontSize: 10),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

String label(String value) =>
    const {
      'automatic': 'Automatique',
      'manual': 'Manuelle',
      'petrol': 'Essence',
      'diesel': 'Diesel',
      'hybrid': 'Hybride',
      'electric': 'Électrique',
      'new': 'Neuf',
      'used': 'Occasion',
      'available': 'Disponible',
      'sold': 'Vendu',
      'rented': 'Loué',
      'other': 'Indisponible',
    }[value] ??
    value;

class VehicleGrid extends StatelessWidget {
  const VehicleGrid(this.vehicles, {super.key, this.offer});
  final List<Vehicle> vehicles;
  final String? offer;
  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final count = constraints.maxWidth >= 700
          ? 3
          : constraints.maxWidth >= 540
          ? 2
          : 1;
      return Wrap(
        spacing: 16,
        runSpacing: 16,
        children: vehicles
            .map(
              (v) => SizedBox(
                width: (constraints.maxWidth - (count - 1) * 16) / count,
                child: VehicleCard(v, offer: offer),
              ),
            )
            .toList(),
      );
    },
  );
}

String? demoPhoto(Vehicle vehicle, Json photo) {
  if (!vehicle.demo || photo['is_placeholder'] != true) {
    return null;
  }
  final name = const {
    'RAV4': 'rav4',
    '208': '208',
    'C300': 'c300',
    'Kangoo': 'kangoo',
    'Model 3': 'electric',
  }[object(vehicle.json['model'])['name']];
  return name == null ? null : 'assets/images/vehicle-$name.webp';
}
