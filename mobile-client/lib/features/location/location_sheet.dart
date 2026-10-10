import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../../shared/widgets.dart';

Future<void> chooseLocation(BuildContext context) => showModalBottomSheet<void>(
  context: context,
  isScrollControlled: true,
  builder: (_) => const LocationSheet(),
);

class LocationSheet extends ConsumerStatefulWidget {
  const LocationSheet({super.key});
  @override
  ConsumerState<LocationSheet> createState() => _LocationSheetState();
}

class _LocationSheetState extends ConsumerState<LocationSheet> {
  String? country, city, district;
  List<Json> cities = [], districts = [];
  bool busy = false;
  int generation = 0;
  Future<void> loadCities(String value) async {
    final current = ++generation;
    setState(() {
      country = value;
      city = district = null;
      cities = [];
      districts = [];
      busy = true;
    });
    try {
      final rows = await ref.read(vehiclesProvider).references('cities', {
        'country_code': value,
      });
      if (mounted && current == generation) {
        setState(() => cities = rows);
      }
    } catch (e) {
      if (mounted) {
        showError(context, e);
      }
    } finally {
      if (mounted && current == generation) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final countries = ref.watch(countriesProvider);
    return SafeArea(
      child: SingleChildScrollView(
        padding: EdgeInsets.fromLTRB(
          24,
          24,
          24,
          24 + MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Votre localisation',
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 16),
            const Text(
              'Utiliser ma position pour afficher les véhicules les plus proches.',
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: busy
                  ? null
                  : () async {
                      setState(() => busy = true);
                      try {
                        final coordinates = await ref
                            .read(locationRepositoryProvider)
                            .locate();
                        ref.read(locationProvider.notifier).state = coordinates;
                        ref.read(locationLabelProvider.notifier).state =
                            'Autour de ma position';
                        if (context.mounted) Navigator.pop(context);
                      } catch (e) {
                        if (context.mounted) showError(context, e);
                      } finally {
                        if (mounted) setState(() => busy = false);
                      }
                    },
              icon: const Icon(Icons.my_location),
              label: Text(busy ? 'Localisation…' : 'Utiliser ma position'),
            ),
            const SizedBox(height: 24),
            const Text('Ou choisissez une localisation manuelle'),
            const SizedBox(height: 16),
            countries.when(
              data: (rows) => DropdownButtonFormField<String>(
                initialValue: country,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'Pays'),
                items: rows
                    .map(
                      (r) => DropdownMenuItem(
                        value: text(r['code']),
                        child: Text(text(r['name'])),
                      ),
                    )
                    .toList(),
                onChanged: busy ? null : (v) => loadCities(v!),
              ),
              loading: () => const LinearProgressIndicator(),
              error: (e, _) =>
                  ErrorPanel(e, () => ref.invalidate(countriesProvider)),
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              key: ValueKey('city:$country'),
              initialValue: city,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Ville (facultatif)',
              ),
              items: cities
                  .map(
                    (r) => DropdownMenuItem(
                      value: text(r['id']),
                      child: Text(text(r['name'])),
                    ),
                  )
                  .toList(),
              onChanged: busy
                  ? null
                  : (v) async {
                      final current = ++generation;
                      setState(() {
                        city = v;
                        district = null;
                        districts = [];
                        busy = true;
                      });
                      try {
                        final rows = await ref
                            .read(vehiclesProvider)
                            .references('districts', {'city_id': v});
                        if (mounted && current == generation) {
                          setState(() => districts = rows);
                        }
                      } catch (e) {
                        if (context.mounted) {
                          showError(context, e);
                        }
                      } finally {
                        if (mounted && current == generation) {
                          setState(() => busy = false);
                        }
                      }
                    },
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              key: ValueKey('district:$city'),
              initialValue: district,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Commune (facultatif)',
              ),
              items: districts
                  .map(
                    (r) => DropdownMenuItem(
                      value: text(r['id']),
                      child: Text(text(r['name'])),
                    ),
                  )
                  .toList(),
              onChanged: busy ? null : (v) => setState(() => district = v),
            ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: country == null || busy
                  ? null
                  : () {
                      ref.read(locationProvider.notifier).state = {
                        'location_mode': 'rank',
                        'country_code': country,
                        if (city != null) 'city_id': city,
                        if (district != null) 'district_id': district,
                      };
                      final rows =
                          ref.read(countriesProvider).valueOrNull ?? [];
                      ref.read(locationLabelProvider.notifier).state = [
                        districts
                            .where((r) => text(r['id']) == district)
                            .firstOrNull?['name'],
                        cities
                            .where((r) => text(r['id']) == city)
                            .firstOrNull?['name'],
                        rows
                            .where((r) => r['code'] == country)
                            .firstOrNull?['name'],
                      ].where((v) => v != null).take(2).join(', ');
                      Navigator.pop(context);
                    },
              child: const Text('Appliquer'),
            ),
            TextButton(
              onPressed: () {
                ref.read(locationLabelProvider.notifier).state =
                    'Tous les pays';
                ref.read(locationProvider.notifier).state = {
                  'location_mode': 'rank',
                };
                Navigator.pop(context);
              },
              child: const Text('Tous les pays'),
            ),
          ],
        ),
      ),
    );
  }
}
