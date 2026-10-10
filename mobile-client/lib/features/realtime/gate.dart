import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/providers.dart';
import '../commerce/providers.dart';
import '../commerce/pdf_files.dart';
import 'reverb.dart';

final realtimeProvider = Provider<RealtimeTransport>((ref) {
  final service = ReverbTransport(
    ref.watch(apiProvider),
    ReverbConfig.environment(),
  );
  ref.onDispose(service.dispose);
  return service;
});
final notificationProvider = StateProvider<String?>((_) => null);
final appMessengerKey = GlobalKey<ScaffoldMessengerState>();

class RealtimeGate extends ConsumerStatefulWidget {
  const RealtimeGate({super.key, required this.child});
  final Widget child;
  @override
  ConsumerState<RealtimeGate> createState() => _GateState();
}

class _GateState extends ConsumerState<RealtimeGate>
    with WidgetsBindingObserver {
  StreamSubscription<DomainSignal>? subscription;
  Timer? reconcile;
  bool paused = false;
  final pending = <String, DomainSignal>{};
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    final transport = ref.read(realtimeProvider);
    subscription = transport.signals.listen(receive);
    transport.identity(ref.read(authProvider).valueOrNull?.id);
  }

  void receive(DomainSignal signal) {
    if (!mounted || paused) return;
    pending['${signal.type}:${signal.data['id'] ?? signal.data['vehicle_id'] ?? ''}'] =
        signal;
    reconcile?.cancel();
    reconcile = Timer(const Duration(milliseconds: 100), flush);
  }

  void flush() {
    if (!mounted || paused) return;
    final events = pending.values.toList();
    pending.clear();
    final all = events.any((e) => e.type == 'Reconnected');
    if (all || events.any((e) => e.type.startsWith('Vehicle'))) {
      ref.invalidate(vehicleProvider);
      ref.invalidate(availabilityProvider);
      ref.invalidate(homeProvider);
      ref.invalidate(catalogueProvider);
      ref.invalidate(favoritesProvider);
      ref.invalidate(shopProvider);
      ref.invalidate(accountSuggestionsProvider);
    }
    if (all ||
        events.any(
          (e) => e.type.startsWith('Reservation') || e.type.startsWith('Order'),
        )) {
      ref.invalidate(transactionProvider);
      ref.invalidate(historyProvider);
      ref.invalidate(receiptListProvider);
      ref.invalidate(receiptProvider);
      ref.invalidate(accountProvider);
    }
    for (final signal in events) {
      final message = switch (signal.type) {
        'ReservationConfirmed' => 'Votre réservation a été confirmée.',
        'OrderConfirmed' => 'Votre achat a été confirmé.',
        'OrderCompleted' => 'La remise de votre véhicule a été enregistrée.',
        _ => null,
      };
      if (message != null) {
        appMessengerKey.currentState?.showSnackBar(
          SnackBar(content: Text(message)),
        );
      }
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.inactive) {
      return; // Native picker/share sheet overlays are brief.
    }
    final active = state == AppLifecycleState.resumed;
    paused = !active;
    pending.clear();
    reconcile?.cancel();
    ref.read(realtimeProvider).foreground(active);
    if (active) {
      receive(
        const DomainSignal('Reconnected', {}),
      ); // REST refresh even without configured WebSocket.
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    reconcile?.cancel();
    unawaited(subscription?.cancel());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(authProvider, (old, next) {
      final previous = old?.valueOrNull?.id, current = next.valueOrNull?.id;
      if (previous == current && next.isLoading) return;
      if (previous != current) {
        pending.clear();
        reconcile?.cancel();
        if (previous != null) {
          unawaited(
            ref.read(pdfFilesProvider).clear().catchError((Object _) {}),
          );
        }
        ref.invalidate(historyProvider);
        ref.invalidate(transactionProvider);
        ref.invalidate(receiptListProvider);
        ref.invalidate(receiptProvider);
      }
      ref.read(realtimeProvider).identity(current);
    });
    return widget.child;
  }
}
