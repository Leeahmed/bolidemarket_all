import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import 'models.dart';
import 'repository.dart';

final commerceRepositoryProvider = Provider(
  (ref) => CommerceRepository(ref.watch(apiProvider)),
);
final receiptRepositoryProvider = Provider(
  (ref) => ReceiptRepository(ref.watch(apiProvider)),
);
final availabilityProvider = FutureProvider.autoDispose
    .family<Availability, String>((ref, slug) async {
      final vehicle = await ref.watch(vehicleProvider(slug).future);
      final today = shopToday(text(object(vehicle.json['shop'])['timezone']));
      return ref
          .watch(commerceRepositoryProvider)
          .availability(
            slug,
            from: dateInput(today),
            to: dateInput(today.add(const Duration(days: 365))),
          );
    });
final transactionProvider = FutureProvider.autoDispose
    .family<CommerceRecord, (String, String)>((ref, key) {
      ref.watch(authProvider.select((state) => state.valueOrNull?.id));
      return ref.watch(commerceRepositoryProvider).detail(key.$1, key.$2);
    });
final receiptProvider = FutureProvider.autoDispose.family<Receipt, String>((
  ref,
  key,
) {
  ref.watch(authProvider.select((state) => state.valueOrNull?.id));
  return ref.watch(receiptRepositoryProvider).detail(key);
});
final historyProvider = StateNotifierProvider.autoDispose
    .family<HistoryController, AsyncValue<PageData<CommerceRecord>>, String>((
      ref,
      kind,
    ) {
      ref.watch(authProvider.select((state) => state.valueOrNull?.id));
      return HistoryController(ref.watch(commerceRepositoryProvider), kind)
        ..reload();
    });

class HistoryController
    extends StateNotifier<AsyncValue<PageData<CommerceRecord>>> {
  HistoryController(this.repo, this.kind) : super(const AsyncLoading());
  final CommerceRepository repo;
  final String kind;
  bool busy = false;
  int generation = 0;
  Future<void> reload() async {
    final current = ++generation;
    state = const AsyncLoading();
    try {
      final page = await repo.list(kind);
      if (mounted && current == generation) state = AsyncData(page);
    } catch (e, st) {
      if (mounted && current == generation) state = AsyncError(e, st);
    }
  }

  Future<void> more() async {
    final data = state.valueOrNull;
    if (busy || data == null || !data.hasMore) return;
    busy = true;
    final current = generation;
    try {
      final next = await repo.list(kind, page: data.page + 1);
      if (mounted && current == generation) {
        state = AsyncData(
          PageData(
            [...data.items, ...next.items],
            next.page,
            next.lastPage,
            next.total,
          ),
        );
      }
    } finally {
      busy = false;
    }
  }
}

final receiptListProvider =
    StateNotifierProvider.autoDispose<
      ReceiptListController,
      AsyncValue<PageData<Receipt>>
    >((ref) {
      ref.watch(authProvider.select((state) => state.valueOrNull?.id));
      return ReceiptListController(ref.watch(receiptRepositoryProvider))
        ..reload();
    });

class ReceiptListController
    extends StateNotifier<AsyncValue<PageData<Receipt>>> {
  ReceiptListController(this.repo) : super(const AsyncLoading());
  final ReceiptRepository repo;
  bool busy = false;
  int generation = 0;
  Future<void> reload() async {
    final current = ++generation;
    state = const AsyncLoading();
    try {
      final data = await repo.list();
      if (mounted && current == generation) state = AsyncData(data);
    } catch (e, st) {
      if (mounted && current == generation) state = AsyncError(e, st);
    }
  }

  Future<void> more() async {
    final data = state.valueOrNull;
    if (busy || data == null || !data.hasMore) return;
    final current = generation;
    busy = true;
    try {
      final next = await repo.list(page: data.page + 1);
      if (mounted && current == generation) {
        state = AsyncData(
          PageData(
            [...data.items, ...next.items],
            next.page,
            next.lastPage,
            next.total,
          ),
        );
      }
    } finally {
      busy = false;
    }
  }
}

void refreshCommerce(Ref ref) {
  ref.invalidate(accountProvider);
  ref.invalidate(historyProvider);
  ref.invalidate(transactionProvider);
  ref.invalidate(receiptListProvider);
  ref.invalidate(receiptProvider);
}
