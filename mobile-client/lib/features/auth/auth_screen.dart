import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/models.dart';
import '../../core/phone.dart';
import '../../core/providers.dart';
import '../../core/api.dart';
import '../../core/theme.dart';
import '../../shared/widgets.dart';

String safeDestination(String? value) {
  if (value == null ||
      !value.startsWith('/') ||
      value.startsWith('//') ||
      value.contains(RegExp(r'[\\\s]'))) {
    return '/account';
  }
  final uri = Uri.tryParse(value);
  if (uri == null ||
      !RegExp(
        r'^/(home|marketplace|vehicle/|shop/|account|profile)',
      ).hasMatch(uri.path)) {
    return '/account';
  }
  return value;
}

class AuthScreen extends ConsumerStatefulWidget {
  const AuthScreen({super.key, this.register = false, this.redirect});
  final bool register;
  final String? redirect;
  @override
  ConsumerState<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends ConsumerState<AuthScreen> {
  final form = GlobalKey<FormState>();
  final fields = <String, TextEditingController>{
    for (final key in [
      'first_name',
      'last_name',
      'email',
      'phone',
      'password',
      'password_confirmation',
    ])
      key: TextEditingController(),
  };
  String? country;
  bool busy = false, obscure = true;
  ApiFailure? error;
  @override
  void dispose() {
    for (final controller in fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> submit() async {
    if (!form.currentState!.validate()) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final repository = ref.read(authRepositoryProvider);
      if (widget.register) {
        final payload = {
          for (final entry in fields.entries) entry.key: entry.value.text,
          'country_code': country,
          'phone': normalizePhone(fields['phone']!.text, country!),
        };
        await repository.register(payload);
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Compte créé. Connectez-vous pour continuer.'),
          ),
        );
        context.go(
          '/login?redirect=${Uri.encodeComponent(safeDestination(widget.redirect))}',
        );
      } else {
        await ref
            .read(authProvider.notifier)
            .login(fields['email']!.text, fields['password']!.text);
        if (mounted) context.go(safeDestination(widget.redirect));
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

  Widget input(String key, String title, {bool password = false}) {
    final server = error?.fields[key];
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: TextFormField(
        controller: fields[key],
        obscureText: password && obscure,
        autofillHints: key == 'email'
            ? [AutofillHints.email]
            : password
            ? [
                widget.register
                    ? AutofillHints.newPassword
                    : AutofillHints.password,
              ]
            : null,
        keyboardType: key == 'email'
            ? TextInputType.emailAddress
            : key == 'phone'
            ? TextInputType.phone
            : TextInputType.text,
        decoration: InputDecoration(
          labelText: title,
          errorText: server is List ? server.join(' ') : server?.toString(),
          suffixIcon: password
              ? IconButton(
                  tooltip: 'Afficher le mot de passe',
                  onPressed: () => setState(() => obscure = !obscure),
                  icon: Icon(
                    obscure
                        ? Icons.visibility_outlined
                        : Icons.visibility_off_outlined,
                  ),
                )
              : null,
        ),
        validator: (value) {
          if (value == null || value.trim().isEmpty) return 'Champ requis';
          if (key == 'email' &&
              !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(value)) {
            return 'Adresse e-mail invalide';
          }
          if (widget.register &&
              key == 'password' &&
              (value.length < 12 ||
                  !RegExp(r'[A-Z]').hasMatch(value) ||
                  !RegExp(r'[a-z]').hasMatch(value) ||
                  !RegExp(r'[0-9]').hasMatch(value) ||
                  !RegExp(r'[^a-zA-Z0-9]').hasMatch(value))) {
            return '12 caractères, majuscule, minuscule, chiffre et symbole.';
          }
          if (key == 'password_confirmation' &&
              value != fields['password']!.text) {
            return 'Les mots de passe sont différents.';
          }
          if (key == 'phone' && country != null) {
            try {
              normalizePhone(value, country!);
            } catch (_) {
              return 'Numéro invalide pour ce pays.';
            }
          }
          return null;
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final countries = ref.watch(countriesProvider);
    final rows = countries.valueOrNull ?? [];
    country ??= rows.any((r) => r['code'] == 'CI')
        ? 'CI'
        : rows.isNotEmpty
        ? text(rows.first['code'])
        : null;
    final selected = rows.where((r) => r['code'] == country).firstOrNull;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Votre espace client'),
        leading: IconButton(
          tooltip: 'Accueil',
          onPressed: () => context.go('/home'),
          icon: const Icon(Icons.arrow_back),
        ),
      ),
      body: SingleChildScrollView(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 560),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(
                  height: 170,
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      SafePhoto(
                        '',
                        asset:
                            'assets/images/auth-${widget.register ? 'register' : 'login'}.webp',
                      ),
                      Container(color: AppColors.carbon.withValues(alpha: .45)),
                      const Center(child: BrandLogo(dark: true)),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: AutofillGroup(
                    child: Form(
                      key: form,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            widget.register
                                ? 'Votre route commence ici.'
                                : 'Bon retour parmi nous.',
                            style: AppTypography.title,
                          ),
                          const SizedBox(height: 24),
                          if (widget.register) ...[
                            input('first_name', 'Prénom'),
                            input('last_name', 'Nom'),
                          ],
                          input('email', 'Email'),
                          if (widget.register) ...[
                            countries.when(
                              data: (_) => DropdownButtonFormField<String>(
                                initialValue: country,
                                isExpanded: true,
                                decoration: const InputDecoration(
                                  labelText: 'Pays',
                                ),
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
                                    : (v) => setState(() => country = v),
                                validator: (v) =>
                                    v == null ? 'Choisissez un pays' : null,
                              ),
                              loading: () => const LinearProgressIndicator(),
                              error: (e, _) => ErrorPanel(
                                e,
                                () => ref.invalidate(countriesProvider),
                              ),
                            ),
                            const SizedBox(height: 16),
                            if (selected != null)
                              Text('Indicatif : ${selected['phone_code']}'),
                            input('phone', 'Téléphone national'),
                          ],
                          input('password', 'Mot de passe', password: true),
                          if (widget.register)
                            input(
                              'password_confirmation',
                              'Confirmation',
                              password: true,
                            ),
                          if (error != null)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 16),
                              child: Text(
                                error!.message,
                                style: const TextStyle(color: AppColors.carbon),
                              ),
                            ),
                          FilledButton(
                            onPressed:
                                busy || (widget.register && country == null)
                                ? null
                                : submit,
                            child: Text(
                              busy
                                  ? 'Veuillez patienter…'
                                  : widget.register
                                  ? 'Créer mon compte'
                                  : 'Se connecter',
                            ),
                          ),
                          TextButton(
                            onPressed: () => context.go(
                              '/${widget.register ? 'login' : 'register'}?redirect=${Uri.encodeComponent(safeDestination(widget.redirect))}',
                            ),
                            child: Text(
                              widget.register
                                  ? 'Déjà inscrit ? Connexion'
                                  : 'Créer un compte',
                            ),
                          ),
                          if (!widget.register)
                            TextButton(
                              onPressed: busy
                                  ? null
                                  : () async {
                                      final email = fields['email']!.text
                                          .trim();
                                      if (!email.contains('@')) {
                                        setState(
                                          () => error = const ApiFailure(
                                            'Saisissez votre e-mail pour recevoir un lien.',
                                          ),
                                        );
                                        return;
                                      }
                                      setState(() => busy = true);
                                      try {
                                        await ref
                                            .read(authRepositoryProvider)
                                            .forgot(email);
                                        if (context.mounted) {
                                          ScaffoldMessenger.of(
                                            context,
                                          ).showSnackBar(
                                            const SnackBar(
                                              content: Text(
                                                'Si le compte existe, un lien de réinitialisation sera envoyé.',
                                              ),
                                            ),
                                          );
                                        }
                                      } catch (e) {
                                        if (context.mounted) {
                                          showError(context, e);
                                        }
                                      } finally {
                                        if (mounted) {
                                          setState(() => busy = false);
                                        }
                                      }
                                    },
                              child: const Text('Mot de passe oublié'),
                            ),
                          const DemoNotice(),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
