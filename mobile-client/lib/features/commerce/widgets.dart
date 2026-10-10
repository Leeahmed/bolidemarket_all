import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../shared/widgets.dart';
import 'models.dart';

class SimulationNotice extends StatelessWidget {
  const SimulationNotice({super.key});
  @override
  Widget build(BuildContext context) => const Card(
    color: AppColors.carbon,
    child: Padding(
      padding: EdgeInsets.all(16),
      child: Text(
        'Mode démonstration\nAucun paiement réel ne sera effectué.',
        style: TextStyle(color: AppColors.ivory),
      ),
    ),
  );
}

class InfoLine extends StatelessWidget {
  const InfoLine(this.name, this.value, {super.key});
  final String name, value;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: Text(name)),
        const SizedBox(width: 12),
        Flexible(
          child: Text(
            value,
            style: const TextStyle(fontWeight: FontWeight.w600),
            textAlign: TextAlign.right,
          ),
        ),
      ],
    ),
  );
}

class PaymentChoices extends StatelessWidget {
  const PaymentChoices(this.value, this.onChanged, {super.key});
  final DemoPayment value;
  final ValueChanged<DemoPayment> onChanged;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const SimulationNotice(),
      for (final payment in DemoPayment.values)
        Card(
          child: ListTile(
            leading: Icon(
              payment == DemoPayment.card
                  ? Icons.credit_card
                  : payment == DemoPayment.mobileMoney
                  ? Icons.phone_android
                  : payment == DemoPayment.cash
                  ? Icons.payments_outlined
                  : Icons.account_balance_outlined,
            ),
            title: Text(payment.label),
            subtitle: Text(
              payment == DemoPayment.card
                  ? 'Démonstration — aucune donnée bancaire'
                  : 'Simulation uniquement',
            ),
            trailing: Icon(
              value == payment
                  ? Icons.radio_button_checked
                  : Icons.radio_button_off,
              color: value == payment ? AppColors.orange : null,
            ),
            onTap: () => onChanged(payment),
          ),
        ),
      const SizedBox(height: 12),
      const Text(
        'La demande sera enregistrée en attente. Le professionnel doit la confirmer.',
      ),
    ],
  );
}

class TransactionTile extends StatelessWidget {
  const TransactionTile(this.record, this.kind, {super.key});
  final CommerceRecord record;
  final String kind;
  @override
  Widget build(BuildContext context) => Card(
    child: ListTile(
      leading: SizedBox(
        width: 48,
        height: 48,
        child: SnapshotPhoto(record.vehicle),
      ),
      title: Text(record.title),
      subtitle: Text(
        '${record.reference}\n${statusLabel(record.status)} · ${record.total.format()}\n${text(record.shop['name'])}${kind == 'reservations' ? ' · ${displayDate(record.json['starts_at'], text(record.json['shop_timezone']))} → ${displayDate(record.json['ends_at'], text(record.json['shop_timezone']))}' : ' · ${displayDate(record.json['created_at'])}'}',
      ),
      isThreeLine: true,
      trailing: const Icon(Icons.chevron_right),
      onTap: () =>
          context.push('/account/$kind/${Uri.encodeComponent(record.id)}'),
    ),
  );
}

class MarketEmpty extends StatelessWidget {
  const MarketEmpty(this.message, {super.key});
  final String message;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(24),
    child: Column(
      children: [
        Text(message),
        const SizedBox(height: 16),
        FilledButton(
          onPressed: () => context.go('/marketplace'),
          child: const Text('Explorer le marché'),
        ),
      ],
    ),
  );
}

Widget recordPhoto(Json snapshot, {double height = 160}) =>
    SizedBox(height: height, child: SnapshotPhoto(snapshot));

class SnapshotPhoto extends ConsumerWidget {
  const SnapshotPhoto(this.snapshot, {super.key});
  final Json snapshot;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final slug = text(snapshot['slug']);
    final vehicle = slug.isEmpty
        ? null
        : ref.watch(vehicleProvider(slug)).valueOrNull;
    final photo = object(vehicle?.json['primary_image']);
    return SafePhoto(
      text(photo['url']),
      asset: vehicle == null ? null : demoPhoto(vehicle, photo),
    );
  }
}
