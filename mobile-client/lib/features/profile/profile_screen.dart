import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/phone.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../shared/widgets.dart';

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});
  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  final form = GlobalKey<FormState>();
  final fields = <String, TextEditingController>{};
  String? country, city;
  List<Json> cities = [];
  Uint8List? preview;
  String? filename;
  bool busy = false;
  int generation = 0;
  ApiFailure? error;
  @override
  void initState() {
    super.initState();
    final user = ref.read(authProvider).valueOrNull;
    for (final key in ['first_name', 'last_name', 'phone']) {
      fields[key] = TextEditingController(text: text(user?.json[key]));
    }
    country = user?.country;
    city = text(user?.json['city_id']).isEmpty
        ? null
        : text(user?.json['city_id']);
    Future.microtask(() => loadCities(country));
  }

  Future<void> loadCities(String? code) async {
    if (code == null || code.isEmpty) return;
    final current = ++generation;
    try {
      final rows = await ref.read(vehiclesProvider).references('cities', {
        'country_code': code,
      });
      if (mounted && generation == current) setState(() => cities = rows);
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  void dispose() {
    for (final c in fields.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> pick() async {
    try {
      final image = await ImagePicker().pickImage(
        source: ImageSource.gallery,
        maxWidth: 2000,
        maxHeight: 2000,
        imageQuality: 85,
      );
      if (image == null) return;
      final bytes = await image.readAsBytes();
      if (bytes.length > 3 * 1024 * 1024) {
        throw const ApiFailure('Choisissez une image de moins de 3 Mo.');
      }
      if (mounted) {
        setState(() {
          preview = bytes;
          filename = image.name;
        });
      }
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  Future<void> save() async {
    if (!form.currentState!.validate()) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final user = await ref.read(accountRepositoryProvider).update({
        for (final entry in fields.entries) entry.key: entry.value.text,
        'phone': normalizePhone(fields['phone']!.text, country!),
        'country_code': country,
        'city_id': city,
        'district_id':
            country == ref.read(authProvider).valueOrNull?.country &&
                city == ref.read(authProvider).valueOrNull?.json['city_id']
            ? ref.read(authProvider).valueOrNull?.json['district_id']
            : null,
      });
      ref.read(authProvider.notifier).setUser(user);
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Profil mis à jour.')));
      }
    } catch (e) {
      if (mounted) {
        setState(
          () => error = e is FormatException
              ? ApiFailure(e.message)
              : ApiFailure.from(e),
        );
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authProvider).valueOrNull;
    final countries = ref.watch(countriesProvider);
    final selected = countries.valueOrNull
        ?.where((r) => r['code'] == country)
        .firstOrNull;
    return Scaffold(
      appBar: AppBar(title: const Text('Mon profil')),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Center(
            child: ClipOval(
              child: SizedBox(
                width: 100,
                height: 100,
                child: preview == null
                    ? SafePhoto(text(user?.json['avatar_url']))
                    : Image.memory(preview!, fit: BoxFit.cover),
              ),
            ),
          ),
          TextButton.icon(
            onPressed: busy ? null : pick,
            icon: const Icon(Icons.photo_library_outlined),
            label: const Text('Choisir dans la galerie'),
          ),
          if (preview != null)
            FilledButton(
              onPressed: busy
                  ? null
                  : () async {
                      setState(() => busy = true);
                      try {
                        final updated = await ref
                            .read(accountRepositoryProvider)
                            .avatar(preview!, filename!);
                        ref.read(authProvider.notifier).setUser(updated);
                        if (mounted) {
                          setState(() {
                            preview = null;
                            filename = null;
                          });
                        }
                      } catch (e) {
                        if (context.mounted) showError(context, e);
                      } finally {
                        if (mounted) setState(() => busy = false);
                      }
                    },
              child: Text(busy ? 'Envoi…' : 'Enregistrer la photo'),
            ),
          const SizedBox(height: 24),
          Form(
            key: form,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final entry in [
                  ('first_name', 'Prénom'),
                  ('last_name', 'Nom'),
                  ('phone', 'Téléphone'),
                ])
                  Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: TextFormField(
                      controller: fields[entry.$1],
                      keyboardType: entry.$1 == 'phone'
                          ? TextInputType.phone
                          : TextInputType.text,
                      decoration: InputDecoration(
                        labelText: entry.$2,
                        errorText: (error?.fields[entry.$1] as List?)?.join(
                          ' ',
                        ),
                      ),
                      validator: (v) =>
                          v?.trim().isEmpty != false ? 'Champ requis' : null,
                    ),
                  ),
                TextFormField(
                  initialValue: text(user?.json['email']),
                  readOnly: true,
                  decoration: const InputDecoration(
                    labelText: 'E-mail (non modifiable)',
                  ),
                ),
                const SizedBox(height: 16),
                countries.when(
                  data: (rows) => DropdownButtonFormField<String>(
                    initialValue: rows.any((r) => r['code'] == country)
                        ? country
                        : null,
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
                    onChanged: busy
                        ? null
                        : (v) {
                            setState(() {
                              country = v;
                              city = null;
                              cities = [];
                            });
                            loadCities(v);
                          },
                    validator: (v) => v == null ? 'Choisissez un pays' : null,
                  ),
                  loading: () => const LinearProgressIndicator(),
                  error: (e, _) =>
                      ErrorPanel(e, () => ref.invalidate(countriesProvider)),
                ),
                const SizedBox(height: 16),
                DropdownButtonFormField<String>(
                  key: ValueKey('$country:$city:${cities.length}'),
                  initialValue: cities.any((r) => r['id'] == city)
                      ? city
                      : null,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Ville'),
                  items: [
                    const DropdownMenuItem<String>(
                      value: null,
                      child: Text('Non renseignée'),
                    ),
                    ...cities.map(
                      (r) => DropdownMenuItem(
                        value: text(r['id']),
                        child: Text(text(r['name'])),
                      ),
                    ),
                  ],
                  onChanged: busy ? null : (v) => setState(() => city = v),
                ),
                const SizedBox(height: 16),
                Text(
                  'Devise locale : ${selected?['currency_code'] ?? user?.currency ?? '—'}. Les annonces étrangères conservent leur devise.',
                  style: const TextStyle(color: AppColors.muted),
                ),
                if (error != null)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    child: Text(error!.message),
                  ),
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: busy ? null : save,
                  child: Text(
                    busy ? 'Enregistrement…' : 'Enregistrer le profil',
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
