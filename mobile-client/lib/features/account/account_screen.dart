import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../shared/widgets.dart';
import '../commerce/models.dart';
import '../commerce/widgets.dart';

class FavoritesScreen extends ConsumerWidget {
  const FavoritesScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => ListView(
    padding: const EdgeInsets.all(24),
    children: [
      const Text('Mes favoris', style: AppTypography.title),
      const SizedBox(height: 24),
      ref
          .watch(favoritesProvider)
          .when(
            loading: () => const LoadingCards(),
            error: (e, _) =>
                ErrorPanel(e, () => ref.invalidate(favoritesProvider)),
            data: (rows) => rows.isEmpty
                ? Column(
                    children: [
                      const Text('Vos coups de cœur vous attendent ici.'),
                      TextButton(
                        onPressed: () => context.go('/marketplace'),
                        child: const Text('Explorer le marché →'),
                      ),
                    ],
                  )
                : VehicleGrid(rows),
          ),
    ],
  );
}

class AccountScreen extends ConsumerWidget {
  const AccountScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider).valueOrNull;
    if (user == null) {
      return Center(
        child: FilledButton(
          onPressed: () => context.go('/login?redirect=%2Faccount'),
          child: const Text('Se connecter'),
        ),
      );
    }
    final favorites = ref.watch(favoritesProvider);
    final overview = ref.watch(accountProvider);
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Row(
          children: [
            ClipOval(
              child: SizedBox(
                width: 64,
                height: 64,
                child: SafePhoto(text(user.json['avatar_url'])),
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(user.name, style: AppTypography.heading),
                  Text(user.location),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 24),
        Text('Bonjour, ${user.firstName} 👋', style: AppTypography.title),
        if (user.json['email_verification_required'] == true)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 16),
            child: Text(
              'Vérifiez votre e-mail avant les parcours d’achat et de location.',
            ),
          ),
        Card(
          color: AppColors.carbon,
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'MARKETPLACE',
                  style: TextStyle(color: AppColors.ivory, letterSpacing: 2),
                ),
                const SizedBox(height: 10),
                const Text(
                  'Trouver votre prochain bolide',
                  style: TextStyle(
                    color: AppColors.ivory,
                    fontSize: 24,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: () => context.go('/marketplace'),
                  child: const Text('Explorer →'),
                ),
              ],
            ),
          ),
        ),
        const SectionTitle('Vue d’ensemble'),
        overview.when(
          loading: () => const LinearProgressIndicator(),
          error: (e, _) => ErrorPanel(e, () => ref.invalidate(accountProvider)),
          data: (data) => Column(
            children: [
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  for (final stat in [
                    ('${favorites.valueOrNull?.length ?? '—'}', 'Favoris'),
                    ('${data['reservations_total'] ?? '—'}', 'Réservations'),
                    ('${data['orders_total'] ?? '—'}', 'Achats'),
                  ])
                    SizedBox(
                      width: 96,
                      child: Card(
                        margin: EdgeInsets.zero,
                        color: Colors.white,
                        child: Padding(
                          padding: const EdgeInsets.all(6),
                          child: Column(
                            children: [
                              Text(stat.$1, style: AppTypography.heading),
                              Text(
                                stat.$2,
                                style: const TextStyle(fontSize: 12),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                ],
              ),
              const SectionTitle('Prochaines réservations'),
              for (final reservation
                  in (data['reservations'] as List? ?? [])
                      .map(object)
                      .where(
                        (r) =>
                            [
                              'pending',
                              'confirmed',
                              'active',
                            ].contains(r['status']) &&
                            DateTime.tryParse(
                                  text(r['ends_at']),
                                )?.isAfter(commerceNow()) ==
                                true,
                      )
                      .take(3))
                ListTile(
                  leading: const Icon(Icons.calendar_today_outlined),
                  title: Text(text(reservation['reference'])),
                  subtitle: Text(
                    '${displayDate(reservation['starts_at'], text(reservation['shop_timezone']))} · ${statusLabel(text(reservation['status']))}',
                  ),
                  onTap: () => context.push(
                    '/account/reservations/${reservation['id']}',
                  ),
                ),
              if ((data['reservations'] as List? ?? []).isEmpty)
                const Text('Aucun trajet programmé.'),
              if (data.containsKey('orders')) ...[
                SectionTitle(
                  'Dernières réservations',
                  onTap: () => context.go('/account/reservations'),
                ),
                if ((data['reservations'] as List? ?? []).isEmpty)
                  const Text('Aucune réservation'),
                for (final row in (data['reservations'] as List? ?? []).take(3))
                  TransactionTile(CommerceRecord(object(row)), 'reservations'),
                SectionTitle(
                  'Derniers achats',
                  onTap: () => context.go('/account/orders'),
                ),
                if ((data['orders'] as List? ?? []).isEmpty)
                  const Text('Aucun achat'),
                for (final row in (data['orders'] as List? ?? []).take(3))
                  TransactionTile(CommerceRecord(object(row)), 'orders'),
                SectionTitle(
                  'Derniers reçus',
                  onTap: () => context.go('/account/receipts'),
                ),
                if ((data['receipts'] as List? ?? []).isEmpty)
                  const Text('Aucun reçu'),
                for (final row in (data['receipts'] as List? ?? []).take(3))
                  ListTile(
                    title: Text(text(object(row)['reference'])),
                    subtitle: Text(recordMoney(object(row)).format()),
                    onTap: () => context.push(
                      '/account/receipts/${Uri.encodeComponent(text(object(row)['reference']))}',
                    ),
                  ),
              ],
            ],
          ),
        ),
        SectionTitle(
          'Mes favoris récents',
          onTap: () => context.go('/account/favorites'),
        ),
        favorites.when(
          loading: () => const LoadingCards(),
          error: (e, _) =>
              ErrorPanel(e, () => ref.invalidate(favoritesProvider)),
          data: (rows) => rows.isEmpty
              ? const Text('Enregistrez les véhicules qui vous plaisent.')
              : VehicleGrid(rows.take(3).toList()),
        ),
        const SectionTitle('Suggestions près de moi'),
        ref
            .watch(accountSuggestionsProvider)
            .when(
              loading: () => const LoadingCards(),
              error: (e, _) => ErrorPanel(
                e,
                () => ref.invalidate(accountSuggestionsProvider),
              ),
              data: (rows) => rows.isEmpty
                  ? const Text(
                      'Aucune offre dans votre localisation. Explorez les autres marchés.',
                    )
                  : VehicleGrid(rows),
            ),
        for (final route in [
          ('favorites', 'Favoris', Icons.favorite_border),
          ('reservations', 'Réservations', Icons.calendar_today_outlined),
          ('orders', 'Achats', Icons.directions_car_outlined),
          ('receipts', 'Reçus', Icons.receipt_long_outlined),
        ])
          ListTile(
            leading: Icon(route.$3),
            title: Text(route.$2),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => context.go('/account/${route.$1}'),
          ),
        ListTile(
          leading: const Icon(Icons.person_outline),
          title: const Text('Modifier mon profil'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => context.push('/profile'),
        ),
        OutlinedButton.icon(
          onPressed: () async {
            try {
              await ref.read(authProvider.notifier).logout();
              if (context.mounted) context.go('/home');
            } catch (e) {
              if (context.mounted) showError(context, e);
            }
          },
          icon: const Icon(Icons.logout),
          label: const Text('Se déconnecter'),
        ),
      ],
    );
  }
}

class PreparedScreen extends StatelessWidget {
  const PreparedScreen(this.title, {super.key});
  final String title;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(24),
    child: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Text(title, style: AppTypography.title),
        const SizedBox(height: 16),
        const Text(
          'Ce parcours mobile sera disponible lors de la phase suivante. Vos données restent conservées dans votre compte.',
        ),
        const SizedBox(height: 24),
        FilledButton(
          onPressed: () => context.go('/marketplace'),
          child: const Text('Explorer le marché'),
        ),
      ],
    ),
  );
}
