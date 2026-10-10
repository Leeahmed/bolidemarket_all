import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../shared/widgets.dart';
import 'models.dart';
import 'providers.dart';
import 'widgets.dart';

class HistoryScreen extends ConsumerStatefulWidget {
  const HistoryScreen(this.kind, {super.key});
  final String kind;
  @override
  ConsumerState<HistoryScreen> createState() => _HistoryState();
}

class _HistoryState extends ConsumerState<HistoryScreen> {
  bool more = false;
  @override
  Widget build(BuildContext context) {
    final data = ref.watch(historyProvider(widget.kind));
    return RefreshIndicator(
      onRefresh: () => ref.read(historyProvider(widget.kind).notifier).reload(),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(24),
        children: [
          Text(
            widget.kind == 'reservations' ? 'Mes réservations' : 'Mes achats',
            style: AppTypography.title,
          ),
          const SizedBox(height: 16),
          data.when(
            loading: () => const LoadingCards(),
            error: (e, _) => ErrorPanel(
              e,
              () => ref.invalidate(historyProvider(widget.kind)),
            ),
            data: (page) {
              final rows = page.items
                  .where(
                    (r) => widget.kind != 'orders' || r.json['kind'] == 'sale',
                  )
                  .toList();
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (rows.isEmpty && !page.hasMore)
                    MarketEmpty(
                      widget.kind == 'reservations'
                          ? 'Aucune réservation'
                          : 'Aucun achat',
                    ),
                  for (final row in rows) TransactionTile(row, widget.kind),
                  if (page.hasMore)
                    OutlinedButton(
                      onPressed: more
                          ? null
                          : () async {
                              setState(() => more = true);
                              try {
                                await ref
                                    .read(historyProvider(widget.kind).notifier)
                                    .more();
                              } catch (e) {
                                if (context.mounted) showError(context, e);
                              } finally {
                                if (mounted) setState(() => more = false);
                              }
                            },
                      child: Text(more ? 'Chargement…' : 'Voir la suite'),
                    ),
                ],
              );
            },
          ),
          const SimulationNotice(),
        ],
      ),
    );
  }
}

class TransactionScreen extends ConsumerStatefulWidget {
  const TransactionScreen(
    this.kind,
    this.identifier, {
    super.key,
    this.confirmation = false,
  });
  final String kind, identifier;
  final bool confirmation;
  @override
  ConsumerState<TransactionScreen> createState() => _TransactionState();
}

class _TransactionState extends ConsumerState<TransactionScreen> {
  bool cancelling = false;
  @override
  Widget build(BuildContext context) {
    final key = (widget.kind, widget.identifier);
    final data = ref.watch(transactionProvider(key));
    final rental = widget.kind == 'reservations';
    return Scaffold(
      appBar: AppBar(
        title: Text(
          widget.confirmation
              ? 'Confirmation'
              : rental
              ? 'Réservation'
              : 'Achat',
        ),
        leading: IconButton(
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.canPop()
              ? context.pop()
              : context.go('/account/${widget.kind}'),
        ),
      ),
      body: data.when(
        loading: () => const LoadingCards(),
        error: (e, _) =>
            ErrorPanel(e, () => ref.invalidate(transactionProvider(key))),
        data: (record) => ListView(
          padding: const EdgeInsets.all(24),
          children: [
            Text(
              widget.confirmation
                  ? (rental ? 'Réservation enregistrée' : 'Achat enregistré')
                  : record.title,
              style: AppTypography.title,
            ),
            const SizedBox(height: 16),
            recordPhoto(record.vehicle),
            InfoLine('Référence', record.reference),
            InfoLine('Véhicule', record.title),
            InfoLine('Professionnel', text(record.shop['name'])),
            InfoLine('Statut', statusLabel(record.status)),
            if (rental) ...[
              InfoLine(
                'Départ',
                displayDate(
                  record.json['starts_at'],
                  text(record.json['shop_timezone']),
                ),
              ),
              InfoLine(
                'Retour',
                displayDate(
                  record.json['ends_at'],
                  text(record.json['shop_timezone']),
                ),
              ),
              InfoLine('Jours facturés', text(record.json['billable_days'])),
            ],
            InfoLine(
              'Sous-total',
              recordMoney(record.json, 'subtotal_minor').format(),
            ),
            InfoLine('Frais', recordMoney(record.json, 'fees_minor').format()),
            InfoLine('Total', record.total.format()),
            InfoLine('Devise', text(record.json['currency'])),
            InfoLine(
              'Paiement DEMO',
              DemoPayment.labelFor(record.json['payment_method_demo']),
            ),
            InfoLine(
              'État du paiement',
              text(
                    object(
                      rental
                          ? object(record.json['order'])['payment']
                          : record.json['payment'],
                    )['status'],
                  ).isEmpty
                  ? 'En attente de confirmation professionnelle'
                  : statusLabel(
                      text(
                        object(
                          rental
                              ? object(record.json['order'])['payment']
                              : record.json['payment'],
                        )['status'],
                      ),
                    ),
            ),
            if (record.status == 'pending')
              const Text(
                'Le professionnel doit confirmer votre demande. Aucun paiement réel n’a été effectué.',
              ),
            if (record.json['handover'] is Map) ...[
              const SectionTitle('Remise du véhicule'),
              InfoLine(
                'Mode',
                const {
                      'self': 'Retrait personnel',
                      'proxy': 'Personne mandatée',
                      'delivery': 'Demande de livraison',
                    }[object(record.json['handover'])['mode']] ??
                    '—',
              ),
              InfoLine(
                'Date',
                displayDate(
                  object(record.json['handover'])['scheduled_at'],
                  text(object(record.json['handover'])['timezone']),
                ),
              ),
              InfoLine(
                'Rendez-vous UTC',
                text(object(record.json['handover'])['scheduled_at']),
              ),
              InfoLine(
                'Contact',
                text(object(record.json['handover'])['contact_name']),
              ),
              InfoLine(
                'Téléphone',
                text(object(record.json['handover'])['contact_phone']),
              ),
              if (object(record.json['handover'])['mode'] == 'delivery')
                InfoLine(
                  'Adresse',
                  '${text(object(record.json['handover'])['city'])} · ${text(object(record.json['handover'])['address'])}',
                ),
              const Text(
                'À confirmer avec le professionnel. Aucun chauffeur automatiquement affecté.',
              ),
            ],
            if (record.receipt.isNotEmpty)
              FilledButton.icon(
                icon: const Icon(Icons.receipt_long_outlined),
                onPressed: () => context.push(
                  '/account/receipts/${Uri.encodeComponent(record.receipt)}',
                ),
                label: const Text('Voir / télécharger mon reçu'),
              )
            else
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 12),
                child: Text(
                  'Le reçu sera disponible après la confirmation du paiement DEMO par le professionnel.',
                ),
              ),
            if (rental && record.canCancelAt(commerceNow()))
              OutlinedButton(
                onPressed: cancelling
                    ? null
                    : () async {
                        final agree = await showDialog<bool>(
                          context: context,
                          builder: (c) => AlertDialog(
                            title: const Text('Annuler la réservation ?'),
                            content: const Text(
                              'La disponibilité sera libérée si le serveur autorise l’annulation. Un paiement DEMO déjà confirmé restera dans l’historique, sans remboursement.',
                            ),
                            actions: [
                              TextButton(
                                onPressed: () => Navigator.pop(c, false),
                                child: const Text('Conserver'),
                              ),
                              FilledButton(
                                onPressed: () => Navigator.pop(c, true),
                                child: const Text('Annuler la réservation'),
                              ),
                            ],
                          ),
                        );
                        if (agree != true || !mounted) return;
                        setState(() => cancelling = true);
                        try {
                          await ref
                              .read(commerceRepositoryProvider)
                              .cancel(record.id);
                          if (!mounted) return;
                          ref.invalidate(transactionProvider);
                          ref.invalidate(historyProvider);
                          ref.invalidate(accountProvider);
                          ref.invalidate(availabilityProvider);
                          ref.invalidate(vehicleProvider);
                        } catch (e) {
                          if (context.mounted) showError(context, e);
                        } finally {
                          if (mounted) setState(() => cancelling = false);
                        }
                      },
                child: Text(
                  cancelling ? 'Annulation…' : 'Annuler la réservation',
                ),
              ),
            TextButton(
              onPressed: () => context.go('/account/${widget.kind}'),
              child: Text(rental ? 'Voir mes réservations' : 'Voir mes achats'),
            ),
            if (text(record.vehicle['slug']).isNotEmpty)
              TextButton(
                onPressed: () => context.push(
                  '/vehicle/${Uri.encodeComponent(text(record.vehicle['slug']))}',
                ),
                child: const Text('Voir le véhicule'),
              ),
            if (text(record.shop['slug']).isNotEmpty)
              TextButton(
                onPressed: () => context.push(
                  '/shop/${Uri.encodeComponent(text(record.shop['slug']))}',
                ),
                child: const Text('Voir la boutique'),
              ),
            TextButton(
              onPressed: () => context.go('/marketplace'),
              child: const Text('Retour au marché'),
            ),
            const SimulationNotice(),
          ],
        ),
      ),
    );
  }
}
