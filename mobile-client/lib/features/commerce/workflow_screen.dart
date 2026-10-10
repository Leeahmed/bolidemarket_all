import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:phone_numbers_parser/phone_numbers_parser.dart';
import 'package:timezone/timezone.dart' as tz;
import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../shared/widgets.dart';
import 'calendar.dart';
import 'models.dart';
import 'providers.dart';
import 'widgets.dart';

class CommerceWorkflowScreen extends ConsumerStatefulWidget {
  const CommerceWorkflowScreen(
    this.slug, {
    super.key,
    required this.rental,
    this.initialDraft,
  });
  final Json? initialDraft;
  final String slug;
  final bool rental;
  @override
  ConsumerState<CommerceWorkflowScreen> createState() => _WorkflowState();
}

class _WorkflowState extends ConsumerState<CommerceWorkflowScreen> {
  int step = 0;
  bool busy = false, consent = false, uncertain = false, blocked = false;
  String? error;
  DateTime? start, end, meeting;
  TimeOfDay? hour;
  Json? quote, request;
  String? intentKey;
  DemoPayment payment = DemoPayment.mobileMoney;
  String handoverMode = 'self';
  final handoverForm = GlobalKey<FormState>();
  final name = TextEditingController(),
      phone = TextEditingController(),
      city = TextEditingController(),
      address = TextEditingController(),
      notes = TextEditingController();
  Json coordinates = {};
  @override
  void initState() {
    super.initState();
    final user = ref.read(authProvider).valueOrNull;
    name.text = user?.name ?? '';
    phone.text = text(user?.json['phone']);
    final draft = widget.initialDraft;
    if (draft != null) {
      step = draft['step'] as int? ?? 0;
      start = draft['start'] as DateTime?;
      end = draft['end'] as DateTime?;
      quote = draft['quote'] as Json?;
      meeting = draft['meeting'] as DateTime?;
      hour = draft['hour'] as TimeOfDay?;
    }
  }

  @override
  void dispose() {
    for (final c in [name, phone, city, address, notes]) {
      c.dispose();
    }
    super.dispose();
  }

  String zone(Vehicle v) => text(object(v.json['shop'])['timezone']);
  bool offered(Vehicle v) =>
      v.json['inventory_status'] == 'available' &&
      (widget.rental ? v.rental : v.sale);
  Future<void> next(Vehicle v) async {
    if (busy || blocked || !offered(v)) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      if (step == 0 && widget.rental) {
        final availability = await ref.read(
          availabilityProvider(widget.slug).future,
        );
        if (start == null ||
            end == null ||
            !availability.validRange(start!, end!)) {
          throw const ApiFailure(
            'Choisissez une période disponible avec une date de retour après le départ.',
          );
        }
        quote = await ref
            .read(commerceRepositoryProvider)
            .quote(v.id, start!, end!);
      }
      if (step == 1 && !widget.rental) {
        if (!handoverForm.currentState!.validate() ||
            meeting == null ||
            hour == null) {
          throw const ApiFailure(
            'Renseignez les coordonnées, la date et l’heure approximative de remise.',
          );
        }
        final local = tz.TZDateTime(
          shopZone(zone(v)),
          meeting!.year,
          meeting!.month,
          meeting!.day,
          hour!.hour,
          hour!.minute,
        );
        if (!local.isAfter(commerceNow())) {
          throw const ApiFailure(
            'Choisissez un rendez-vous futur dans le fuseau de la boutique.',
          );
        }
      }
      if (step < 2) {
        setState(() => step++);
        return;
      }
      if (!consent) {
        throw const ApiFailure(
          'Confirmez avoir compris le mode démonstration.',
        );
      }
      if (request == null) {
        intentKey = newIntentKey();
        request = widget.rental
            ? reservationRequest(text(quote!['id']), payment)
            : orderRequest(v.id, payment, {
                'mode': handoverMode,
                'scheduled_local':
                    '${dateInput(meeting!)}T${hour!.hour.toString().padLeft(2, '0')}:${hour!.minute.toString().padLeft(2, '0')}',
                'contact_name': name.text.trim(),
                'contact_phone': phone.text.trim(),
                if (handoverMode == 'delivery') ...{
                  'city': city.text.trim(),
                  'address': address.text.trim(),
                  ...coordinates,
                },
                if (notes.text.trim().isNotEmpty) 'notes': notes.text.trim(),
              });
        if (!widget.rental) {
          request!['expected_price_minor'] = v.price('sale')!.amount.toString();
        }
      }
      final repo = ref.read(commerceRepositoryProvider);
      final record = widget.rental
          ? await repo.reserve(request!, intentKey!)
          : await repo.buy(request!, intentKey!);
      if (!mounted) return;
      ref.invalidate(accountProvider);
      ref.invalidate(historyProvider);
      ref.invalidate(receiptListProvider);
      ref.invalidate(availabilityProvider(widget.slug));
      ref.invalidate(vehicleProvider(widget.slug));
      context.go(
        '/${widget.rental ? 'reservation' : 'order'}-confirmation/${Uri.encodeComponent(record.reference)}',
      );
    } catch (e) {
      if (!mounted) return;
      final failure = ApiFailure.from(e);
      setState(() {
        error = commerceError(e, rental: widget.rental);
        uncertain =
            step == 2 &&
            request != null &&
            (failure.status == null || (failure.status ?? 0) >= 500);
        if (!uncertain && step == 2) {
          request = null;
          intentKey = null;
        }
        if (failure.status == 409) {
          if (widget.rental) {
            quote = null;
            start = null;
            end = null;
            step = 0;
          } else if (failure.code == 'PRICE_CHANGED') {
            step = 0;
          } else {
            blocked = true;
          }
        }
      });
      if (failure.status == 409) {
        ref.invalidate(availabilityProvider(widget.slug));
        ref.invalidate(vehicleProvider(widget.slug));
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Widget handover(Vehicle v) => Form(
    key: handoverForm,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('Remise du véhicule', style: AppTypography.heading),
        const Text(
          'Le rendez-vous et toute demande de livraison restent à confirmer avec le professionnel. Aucun chauffeur ni tarif de livraison n’est affecté.',
        ),
        DropdownButtonFormField<String>(
          isExpanded: true,
          initialValue: handoverMode,
          decoration: const InputDecoration(labelText: 'Mode de remise'),
          items: const [
            DropdownMenuItem(value: 'self', child: Text('Je viens moi-même')),
            DropdownMenuItem(
              value: 'proxy',
              child: Text('J’envoie une personne'),
            ),
            DropdownMenuItem(
              value: 'delivery',
              child: Text('Livraison par chauffeur'),
            ),
          ],
          onChanged: (value) => setState(() {
            handoverMode = value!;
            coordinates = {};
          }),
        ),
        TextFormField(
          controller: name,
          decoration: const InputDecoration(labelText: 'Nom du contact'),
          maxLength: 120,
          validator: (s) => (s ?? '').trim().isEmpty ? 'Nom requis' : null,
        ),
        TextFormField(
          controller: phone,
          decoration: const InputDecoration(
            labelText: 'Téléphone du contact avec indicatif (+…)',
          ),
          keyboardType: TextInputType.phone,
          validator: (s) {
            try {
              if (!(s ?? '').startsWith('+') ||
                  !PhoneNumber.parse(s!).isValid()) {
                return 'Numéro international valide requis';
              }
            } catch (_) {
              return 'Numéro invalide';
            }
            return null;
          },
        ),
        if (handoverMode == 'delivery') ...[
          TextFormField(
            controller: city,
            decoration: const InputDecoration(labelText: 'Ville de livraison'),
            maxLength: 120,
            validator: (s) => (s ?? '').trim().isEmpty ? 'Ville requise' : null,
          ),
          TextFormField(
            controller: address,
            decoration: const InputDecoration(labelText: 'Adresse / repère'),
            maxLength: 500,
            validator: (s) =>
                (s ?? '').trim().isEmpty ? 'Adresse requise' : null,
          ),
          TextButton.icon(
            icon: const Icon(Icons.my_location),
            label: Text(
              coordinates.isEmpty
                  ? 'Ajouter ma position (facultatif)'
                  : 'Position ajoutée — remplacer',
            ),
            onPressed: () async {
              final agree = await showDialog<bool>(
                context: context,
                builder: (c) => AlertDialog(
                  title: const Text('Ajouter votre position ?'),
                  content: const Text(
                    'Votre position sera transmise uniquement au professionnel pour cette demande de livraison. Vous pouvez utiliser seulement une adresse.',
                  ),
                  actions: [
                    TextButton(
                      onPressed: () => Navigator.pop(c, false),
                      child: const Text('Sans GPS'),
                    ),
                    FilledButton(
                      onPressed: () => Navigator.pop(c, true),
                      child: const Text('Ajouter'),
                    ),
                  ],
                ),
              );
              if (agree != true) return;
              try {
                final result = await ref
                    .read(locationRepositoryProvider)
                    .locate();
                if (mounted) setState(() => coordinates = result);
              } catch (e) {
                if (mounted) {
                  showError(
                    context,
                    e is FormatException ? ApiFailure(e.message) : e,
                  );
                }
              }
            },
          ),
          if (coordinates.isNotEmpty)
            TextButton(
              onPressed: () => setState(() => coordinates = {}),
              child: const Text('Retirer la position'),
            ),
        ],
        TextButton.icon(
          icon: const Icon(Icons.event),
          label: Text(
            meeting == null
                ? 'Choisir la date'
                : 'Date : ${displayDate(dateInput(meeting!))}',
          ),
          onPressed: () async {
            final today = shopToday(zone(v));
            final selected = await showDatePicker(
              context: context,
              firstDate: today,
              lastDate: today.add(const Duration(days: 365)),
              initialDate: meeting ?? today,
            );
            if (selected != null && mounted) {
              setState(() => meeting = civil(selected));
            }
          },
        ),
        TextButton.icon(
          icon: const Icon(Icons.schedule),
          label: Text(
            hour == null
                ? 'Choisir l’heure approximative'
                : 'Heure : ${hour!.format(context)}',
          ),
          onPressed: () async {
            final selected = await showTimePicker(
              context: context,
              initialTime: hour ?? const TimeOfDay(hour: 12, minute: 0),
            );
            if (selected != null && mounted) setState(() => hour = selected);
          },
        ),
        Text('Fuseau du professionnel : ${zone(v)}'),
        TextFormField(
          controller: notes,
          decoration: const InputDecoration(
            labelText: 'Précisions (facultatif)',
          ),
          maxLength: 1000,
          maxLines: 2,
        ),
      ],
    ),
  );
  Widget summary(Vehicle v) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      SizedBox(
        height: 160,
        child: SafePhoto(
          text(object(v.json['primary_image'])['url']),
          asset: demoPhoto(v, object(v.json['primary_image'])),
        ),
      ),
      const SizedBox(height: 16),
      Text(v.title, style: AppTypography.heading),
      InfoLine('Année', text(v.json['year'])),
      InfoLine('Professionnel', v.shopName),
      InfoLine('Localisation', v.location),
      if (widget.rental && quote != null) ...[
        InfoLine(
          'Départ',
          displayDate(quote!['starts_at'], text(quote!['shop_timezone'])),
        ),
        InfoLine(
          'Retour',
          displayDate(quote!['ends_at'], text(quote!['shop_timezone'])),
        ),
        InfoLine('Nombre de jours', text(quote!['billable_days'])),
        InfoLine(
          'Prix / jour',
          recordMoney(quote!, 'daily_price_minor').format(),
        ),
        InfoLine('Sous-total', recordMoney(quote!).format()),
        InfoLine(
          'Frais',
          Money(
            BigInt.zero,
            text(quote!['currency']),
            quote!['minor_unit'] as int,
          ).format(),
        ),
        InfoLine('Total estimé — devis serveur', recordMoney(quote!).format()),
        InfoLine('Devise', text(quote!['currency'])),
        const Text(
          'Le devis expire après cinq minutes. Le serveur revalide les dates et le prix lors de l’enregistrement.',
        ),
      ] else if (!widget.rental && v.price('sale') != null) ...[
        InfoLine('Prix', v.price('sale')!.format()),
        InfoLine('Devise', v.price('sale')!.currency),
        const Text(
          'Prix indicatif : le serveur relit et fige le montant au moment de la demande.',
        ),
      ],
    ],
  );
  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !busy,
    child: Scaffold(
      appBar: AppBar(
        title: Text(
          widget.rental ? 'Réserver ce véhicule' : 'Acheter ce véhicule',
        ),
        leading: IconButton(
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back),
          onPressed: busy
              ? null
              : () => context.canPop()
                    ? context.pop()
                    : context.go('/marketplace'),
        ),
      ),
      body: ref
          .watch(vehicleProvider(widget.slug))
          .when(
            loading: () => const LoadingCards(),
            error: (e, _) => ErrorPanel(
              e,
              () => ref.invalidate(vehicleProvider(widget.slug)),
            ),
            data: (v) => ListView(
              padding: const EdgeInsets.all(24),
              children: [
                Text(
                  'Étape ${step + 1} sur 3 · ${widget.rental ? ['Dates', 'Résumé', 'Paiement DEMO'][step] : ['Résumé véhicule', 'Confirmation client', 'Paiement DEMO'][step]}',
                  style: AppTypography.heading,
                ),
                const SizedBox(height: 12),
                LinearProgressIndicator(value: (step + 1) / 3),
                const SizedBox(height: 20),
                if (!offered(v) || blocked)
                  const Text(
                    'Ce véhicule n’est plus disponible. Revenez au marché.',
                  ),
                if (error != null)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: Text(
                      error!,
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                  ),
                if (uncertain)
                  const Text(
                    'La réponse n’a pas été reçue. Réessayez la même demande pour éviter un doublon, ou vérifiez votre historique.',
                  ),
                if (step == 0 && widget.rental)
                  ref
                      .watch(availabilityProvider(widget.slug))
                      .when(
                        loading: () => const LinearProgressIndicator(),
                        error: (e, _) => ErrorPanel(
                          e,
                          () =>
                              ref.invalidate(availabilityProvider(widget.slug)),
                        ),
                        data: (availability) => AvailabilityCalendar(
                          availability: availability,
                          start: start,
                          end: end,
                          onChanged: busy
                              ? (_, _) {}
                              : (a, b) => setState(() {
                                  start = a;
                                  end = b;
                                  quote = null;
                                }),
                        ),
                      ),
                if (step == 0 && !widget.rental || step == 1 && widget.rental)
                  summary(v),
                if (step == 1 && !widget.rental) handover(v),
                if (step == 2) ...[
                  AbsorbPointer(
                    absorbing: busy || request != null,
                    child: PaymentChoices(
                      payment,
                      (p) => setState(() => payment = p),
                    ),
                  ),
                  CheckboxListTile(
                    value: consent,
                    onChanged: busy || request != null
                        ? null
                        : (v) => setState(() => consent = v!),
                    title: const Text(
                      'Je comprends qu’il s’agit d’une simulation sans paiement réel.',
                    ),
                  ),
                  if (widget.rental && quote != null)
                    InfoLine('Total estimé', recordMoney(quote!).format()),
                  if (!widget.rental && v.price('sale') != null)
                    InfoLine('Prix', v.price('sale')!.format()),
                ],
                const SizedBox(height: 24),
                FilledButton(
                  onPressed:
                      busy ||
                          blocked ||
                          !offered(v) ||
                          ref.watch(vehicleProvider(widget.slug)).isLoading
                      ? null
                      : () => next(v),
                  child: Text(
                    busy
                        ? 'Enregistrement…'
                        : step == 2
                        ? (uncertain
                              ? 'Réessayer la même demande'
                              : 'Confirmer la demande DEMO')
                        : 'Continuer',
                  ),
                ),
                if (step > 0 && !busy && !uncertain)
                  TextButton(
                    onPressed: () => setState(() {
                      step--;
                      request = null;
                      intentKey = null;
                      error = null;
                    }),
                    child: const Text('Modifier l’étape précédente'),
                  ),
                if (error != null && step == 0 && widget.rental)
                  TextButton(
                    onPressed: () =>
                        ref.invalidate(availabilityProvider(widget.slug)),
                    child: const Text('Choisir d’autres dates'),
                  ),
                if (uncertain)
                  TextButton(
                    onPressed: () => context.go(
                      '/account/${widget.rental ? 'reservations' : 'orders'}',
                    ),
                    child: const Text('Vérifier mon historique'),
                  ),
                const SimulationNotice(),
              ],
            ),
          ),
    ),
  );
}
