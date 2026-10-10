import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/models.dart';
import '../../core/budget.dart';
import '../../core/api.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import 'query.dart';

Future<SearchQuery?> filterSheet(BuildContext context, SearchQuery query) =>
    showModalBottomSheet<SearchQuery>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => FilterSheet(query),
    );

class FilterSheet extends ConsumerStatefulWidget {
  const FilterSheet(this.query, {super.key});
  final SearchQuery query;
  @override
  ConsumerState<FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends ConsumerState<FilterSheet> {
  late SearchQuery draft;
  final references = <String, List<Json>>{};
  final inputs = <String, TextEditingController>{};
  Timer? timer;
  int generation = 0;
  int? total;
  String? error;
  bool loading = true;
  @override
  void initState() {
    super.initState();
    draft = widget.query;
    for (final k in ['year_min', 'year_max', 'min_price', 'max_price']) {
      inputs[k] = TextEditingController(text: text(draft.values[k]));
    }
    Future.microtask(load);
  }

  @override
  void dispose() {
    timer?.cancel();
    for (final c in inputs.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> load() async {
    try {
      final repo = ref.read(vehiclesProvider);
      final keys = [
        'countries',
        'cities',
        'districts',
        'brands',
        'models',
        'categories',
        'currencies',
      ];
      final lists = await Future.wait(keys.map((key) => repo.references(key)));
      if (!mounted) return;
      for (var i = 0; i < keys.length; i++) {
        references[keys[i]] = lists[i];
      }
      for (final key in ['min_price', 'max_price']) {
        if (draft.values[key] != null) {
          inputs[key]!.text = budgetMajor(text(draft.values[key]), exponent);
        }
      }
      setState(() => loading = false);
      count();
    } catch (e) {
      if (mounted) {
        setState(() {
          error = ApiFailure.from(e).message;
          loading = false;
        });
      }
    }
  }

  int get exponent =>
      ((references['currencies'] ?? [])
              .where((r) => r['code'] == draft.values['currency'])
              .firstOrNull?['minor_unit']
          as int?) ??
      0;
  void change(Json values) {
    generation++;
    setState(() {
      draft = draft.copy(values);
      total = null;
      error = null;
    });
    timer?.cancel();
    timer = Timer(const Duration(milliseconds: 400), count);
  }

  Future<void> count() async {
    final current = ++generation;
    try {
      for (final key in ['min_price', 'max_price']) {
        final input = inputs[key]!.text;
        if (input.isNotEmpty && draft.values['currency'] == null) {
          throw const FormatException(
            'Choisissez une devise pour votre budget.',
          );
        }
        draft = draft.copy({
          key: input.isEmpty ? null : budgetMinor(input, exponent),
        });
      }
      final result = await ref.read(vehiclesProvider).search(draft);
      if (mounted && current == generation) {
        setState(() {
          total = result.total;
          error = null;
        });
      }
    } catch (e) {
      if (mounted && current == generation) {
        setState(
          () => error = e is FormatException
              ? e.message
              : ApiFailure.from(e).message,
        );
      }
    }
  }

  Widget select(
    String key,
    String title,
    List<(String, String)> options, {
    void Function(String?)? onChange,
  }) {
    final value = text(draft.values[key]);
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: DropdownButtonFormField<String>(
        key: ValueKey('$key:$value'),
        initialValue: options.any((o) => o.$1 == value) ? value : '',
        isExpanded: true,
        decoration: InputDecoration(labelText: title),
        items: [
          const DropdownMenuItem(value: '', child: Text('Tous')),
          ...options.map(
            (o) => DropdownMenuItem(value: o.$1, child: Text(o.$2)),
          ),
        ],
        onChanged: (v) =>
            onChange != null ? onChange(v == '' ? null : v) : change({key: v}),
      ),
    );
  }

  List<(String, String)> options(
    String kind,
    String key, [
    bool Function(Json)? filter,
  ]) => (references[kind] ?? [])
      .where((r) => filter == null || filter(r))
      .map((r) => (text(r[key]), text(r['name'] ?? r['label'])))
      .toList();
  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: MediaQuery.sizeOf(context).height * .88,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(20),
            child: Row(
              children: [
                const Expanded(
                  child: Text(
                    'Affiner votre recherche',
                    style: AppTypography.heading,
                  ),
                ),
                IconButton(
                  tooltip: 'Fermer',
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close),
                ),
              ],
            ),
          ),
          Expanded(
            child: loading
                ? const Center(child: CircularProgressIndicator())
                : ListView(
                    padding: const EdgeInsets.symmetric(horizontal: 24),
                    children: [
                      select(
                        'listing_type',
                        'Offre',
                        [('sale', 'Acheter'), ('rental', 'Louer')],
                        onChange: (v) {
                          inputs['min_price']!.clear();
                          inputs['max_price']!.clear();
                          change({
                            'listing_type': v,
                            'min_price': null,
                            'max_price': null,
                            'sort': 'newest',
                          });
                        },
                      ),
                      select(
                        'country_code',
                        'Pays',
                        options('countries', 'code'),
                        onChange: (v) {
                          final row = (references['countries'] ?? [])
                              .where((r) => r['code'] == v)
                              .firstOrNull;
                          change({
                            'country_code': v,
                            'city_id': null,
                            'district_id': null,
                            'location_mode': 'filter',
                            'currency': row?['currency_code'],
                            'min_price': null,
                            'max_price': null,
                            'sort': 'newest',
                          });
                          inputs['min_price']!.clear();
                          inputs['max_price']!.clear();
                        },
                      ),
                      select(
                        'city_id',
                        'Ville',
                        options(
                          'cities',
                          'id',
                          (r) =>
                              draft.values['country_code'] == null ||
                              r['country_code'] == draft.values['country_code'],
                        ),
                        onChange: (v) => change({
                          'city_id': v,
                          'district_id': null,
                          'location_mode': 'filter',
                        }),
                      ),
                      select(
                        'district_id',
                        'Commune',
                        options(
                          'districts',
                          'id',
                          (r) => r['city_id'] == draft.values['city_id'],
                        ),
                        onChange: (v) => change({
                          'district_id': v,
                          'location_mode': 'filter',
                        }),
                      ),
                      select(
                        'brand',
                        'Marque',
                        options('brands', 'name'),
                        onChange: (v) => change({'brand': v, 'model': null}),
                      ),
                      select(
                        'model',
                        'Modèle',
                        options('models', 'name', (r) {
                          final brand = (references['brands'] ?? [])
                              .where((b) => b['name'] == draft.values['brand'])
                              .firstOrNull;
                          return brand != null &&
                              text(r['brand_id']) == text(brand['id']);
                        }),
                      ),
                      select(
                        'category',
                        'Catégorie',
                        options('categories', 'slug'),
                      ),
                      select(
                        'currency',
                        'Devise du budget (aucune conversion)',
                        options(
                          'currencies',
                          'code',
                        ).map((o) => (o.$1, o.$1)).toList(),
                        onChange: (v) {
                          inputs['min_price']!.clear();
                          inputs['max_price']!.clear();
                          change({
                            'currency': v,
                            'min_price': null,
                            'max_price': null,
                            'sort': 'newest',
                          });
                        },
                      ),
                      const Text(
                        'Budget dans la devise choisie ; location : tarif / jour.',
                        style: TextStyle(fontSize: 12, color: AppColors.muted),
                      ),
                      const SizedBox(height: 8),
                      for (final entry in [
                        ('min_price', 'Prix minimum'),
                        ('max_price', 'Prix maximum'),
                        ('year_min', 'Année minimum'),
                        ('year_max', 'Année maximum'),
                      ])
                        Padding(
                          padding: const EdgeInsets.only(bottom: 16),
                          child: TextField(
                            controller: inputs[entry.$1],
                            keyboardType: TextInputType.number,
                            decoration: InputDecoration(labelText: entry.$2),
                            onChanged: (v) {
                              try {
                                change({
                                  entry.$1: v.isEmpty
                                      ? null
                                      : entry.$1.endsWith('price')
                                      ? budgetMinor(v, exponent)
                                      : v,
                                });
                              } on FormatException catch (e) {
                                generation++;
                                timer?.cancel();
                                setState(() {
                                  error = e.message;
                                  total = null;
                                });
                              }
                            },
                          ),
                        ),
                      select('fuel_type', 'Carburant', [
                        ('petrol', 'Essence'),
                        ('diesel', 'Diesel'),
                        ('hybrid', 'Hybride'),
                        ('electric', 'Électrique'),
                      ]),
                      select('transmission', 'Transmission', [
                        ('manual', 'Manuelle'),
                        ('automatic', 'Automatique'),
                      ]),
                      select('condition', 'Condition', [
                        ('new', 'Neuf'),
                        ('used', 'Occasion'),
                      ]),
                      CheckboxListTile(
                        title: const Text('Certifié seulement'),
                        value: draft.values['is_certified'] == 1,
                        onChanged: (v) =>
                            change({'is_certified': v == true ? 1 : null}),
                      ),
                      if (error != null) Text(error!),
                      const SizedBox(height: 16),
                    ],
                  ),
          ),
          Padding(
            padding: const EdgeInsets.all(20),
            child: Row(
              children: [
                TextButton(
                  onPressed: () {
                    for (final c in inputs.values) {
                      c.clear();
                    }
                    timer?.cancel();
                    generation++;
                    setState(() {
                      draft = const SearchQuery();
                      total = null;
                      error = null;
                    });
                    count();
                  },
                  child: const Text('Réinitialiser'),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton(
                    onPressed: total == null || error != null
                        ? null
                        : () => Navigator.pop(context, draft),
                    child: Text(
                      total == null ? 'Recherche…' : 'Voir $total résultats',
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}
