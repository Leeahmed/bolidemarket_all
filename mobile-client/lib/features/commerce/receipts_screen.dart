import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/models.dart';
import '../../core/theme.dart';
import '../../shared/widgets.dart';
import 'models.dart';
import 'providers.dart';
import 'pdf_files.dart';
import 'widgets.dart';

class ReceiptActions extends ConsumerStatefulWidget {
  const ReceiptActions(this.reference, {super.key});
  final String reference;
  @override
  ConsumerState<ReceiptActions> createState() => _ReceiptActionsState();
}

class _ReceiptActionsState extends ConsumerState<ReceiptActions> {
  bool busy = false;
  Future<void> perform(bool share, BuildContext buttonContext) async {
    if (busy) return;
    final box = buttonContext.findRenderObject() as RenderBox?;
    final origin = box == null
        ? const Rect.fromLTWH(0, 0, 1, 1)
        : box.localToGlobal(Offset.zero) & box.size;
    setState(() => busy = true);
    try {
      final files = ref.read(pdfFilesProvider);
      final file = await files.download(widget.reference);
      if (!mounted) return;
      if (share) {
        await files.share(file, origin);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'PDF téléchargé dans les documents privés de l’application. Utilisez Partager pour l’exporter ou l’imprimer.',
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      FilledButton.icon(
        onPressed: busy ? null : () => perform(false, context),
        icon: const Icon(Icons.download_outlined),
        label: Text(busy ? 'Téléchargement…' : 'Télécharger le PDF'),
      ),
      const SizedBox(height: 8),
      Builder(
        builder: (c) => OutlinedButton.icon(
          onPressed: busy ? null : () => perform(true, c),
          icon: const Icon(Icons.ios_share),
          label: const Text('Partager le reçu'),
        ),
      ),
    ],
  );
}

class ReceiptsScreen extends ConsumerStatefulWidget {
  const ReceiptsScreen({super.key});
  @override
  ConsumerState<ReceiptsScreen> createState() => _ReceiptsState();
}

class _ReceiptsState extends ConsumerState<ReceiptsScreen> {
  bool more = false;
  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: () => ref.read(receiptListProvider.notifier).reload(),
    child: ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(24),
      children: [
        const Text('Mes reçus', style: AppTypography.title),
        const SizedBox(height: 16),
        ref
            .watch(receiptListProvider)
            .when(
              loading: () => const LoadingCards(),
              error: (e, _) =>
                  ErrorPanel(e, () => ref.invalidate(receiptListProvider)),
              data: (page) => Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (page.items.isEmpty) const MarketEmpty('Aucun reçu'),
                  for (final receipt in page.items)
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text(
                              receipt.reference,
                              style: AppTypography.heading,
                            ),
                            InfoLine(
                              'Type',
                              receipt.json['type'] == 'rental'
                                  ? 'Location'
                                  : 'Vente',
                            ),
                            InfoLine(
                              'Véhicule',
                              text(receipt.vehicle['title']),
                            ),
                            InfoLine(
                              'Professionnel',
                              text(receipt.seller['name']),
                            ),
                            InfoLine('Total', receipt.total.format()),
                            InfoLine(
                              'Date',
                              displayDate(receipt.json['issued_at']),
                            ),
                            TextButton(
                              onPressed: () => context.push(
                                '/account/receipts/${Uri.encodeComponent(receipt.reference)}',
                              ),
                              child: const Text('Voir le reçu'),
                            ),
                            ReceiptActions(receipt.reference),
                          ],
                        ),
                      ),
                    ),
                  if (page.hasMore)
                    OutlinedButton(
                      onPressed: more
                          ? null
                          : () async {
                              setState(() => more = true);
                              try {
                                await ref
                                    .read(receiptListProvider.notifier)
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
              ),
            ),
        const SimulationNotice(),
      ],
    ),
  );
}

class ReceiptDetailScreen extends ConsumerWidget {
  const ReceiptDetailScreen(this.reference, {super.key});
  final String reference;
  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    appBar: AppBar(
      title: const Text('Reçu BolideMarket'),
      leading: IconButton(
        tooltip: 'Retour',
        icon: const Icon(Icons.arrow_back),
        onPressed: () =>
            context.canPop() ? context.pop() : context.go('/account/receipts'),
      ),
    ),
    body: ref
        .watch(receiptProvider(reference))
        .when(
          loading: () => const LoadingCards(),
          error: (e, _) =>
              ErrorPanel(e, () => ref.invalidate(receiptProvider(reference))),
          data: (r) => ListView(
            padding: const EdgeInsets.all(24),
            children: [
              const BrandLogo(dark: false),
              const SizedBox(height: 24),
              const Text('Reçu de démonstration', style: AppTypography.title),
              InfoLine('Référence', r.reference),
              InfoLine('Émis le', displayDate(r.json['issued_at'])),
              const SectionTitle('Client'),
              for (final field in [
                ('Nom', 'name'),
                ('E-mail', 'email'),
                ('Téléphone', 'phone'),
                ('Pays', 'country'),
                ('Ville', 'city'),
              ])
                if (text(r.buyer[field.$2]).isNotEmpty)
                  InfoLine(field.$1, text(r.buyer[field.$2])),
              if (r.buyer.isEmpty)
                const Text('Coordonnées historiques non enregistrées.'),
              const SectionTitle('Professionnel'),
              for (final field in [
                ('Nom', 'name'),
                ('Adresse', 'address'),
                ('E-mail', 'email'),
                ('Téléphone', 'phone'),
              ])
                if (text(r.seller[field.$2]).isNotEmpty)
                  InfoLine(field.$1, text(r.seller[field.$2])),
              const SectionTitle('Véhicule'),
              InfoLine('Modèle', text(r.vehicle['title'])),
              InfoLine('Année', text(r.vehicle['year'])),
              const SectionTitle('Transaction'),
              InfoLine(
                'Type',
                r.json['type'] == 'rental' ? 'Location' : 'Vente',
              ),
              InfoLine('Commande', text(r.transaction['order_reference'])),
              InfoLine('Paiement', text(r.transaction['payment_reference'])),
              InfoLine(
                'Méthode DEMO',
                DemoPayment.labelFor(r.json['payment_method']),
              ),
              InfoLine('Statut', statusLabel(text(r.json['payment_status']))),
              if (r.json['type'] == 'rental') ...[
                InfoLine(
                  'Départ',
                  displayDate(
                    r.transaction['starts_at'],
                    text(r.transaction['timezone']),
                  ),
                ),
                InfoLine(
                  'Retour',
                  displayDate(
                    r.transaction['ends_at'],
                    text(r.transaction['timezone']),
                  ),
                ),
                InfoLine('Jours', text(r.transaction['days'])),
              ],
              InfoLine(
                'Sous-total',
                recordMoney(r.json, 'subtotal_minor').format(),
              ),
              InfoLine('Frais', recordMoney(r.json, 'fees_minor').format()),
              InfoLine('Total', r.total.format()),
              InfoLine('Devise', text(r.json['currency'])),
              const SizedBox(height: 16),
              ReceiptActions(r.reference),
              const SimulationNotice(),
              Text(text(r.json['demo_notice'])),
              const Text(
                'Ce reçu historique est immuable. Il ne constitue pas une facture fiscale.',
              ),
              if (text(r.transaction['reservation_id']).isNotEmpty)
                TextButton(
                  onPressed: () => context.push(
                    '/account/reservations/${text(r.transaction['reservation_id'])}',
                  ),
                  child: const Text('Voir la réservation'),
                ),
              TextButton(
                onPressed: () =>
                    context.push('/account/orders/${text(r.json['order_id'])}'),
                child: const Text('Voir la commande'),
              ),
              if (text(r.vehicle['slug']).isNotEmpty)
                TextButton(
                  onPressed: () => context.push(
                    '/vehicle/${Uri.encodeComponent(text(r.vehicle['slug']))}',
                  ),
                  child: const Text('Voir le véhicule'),
                ),
              if (text(r.seller['slug']).isNotEmpty)
                TextButton(
                  onPressed: () => context.push(
                    '/shop/${Uri.encodeComponent(text(r.seller['slug']))}',
                  ),
                  child: const Text('Voir la boutique'),
                ),
            ],
          ),
        ),
  );
}
