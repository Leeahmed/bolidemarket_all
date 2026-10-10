import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../shared/widgets.dart';

class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});
  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  int page = 0;
  final pages = const [
    ('Trouvez votre prochain bolide.', 'vehicle-rav4.webp'),
    ('Achetez ou louez près de chez vous.', 'vehicle-208.webp'),
    ('Des professionnels, où que vous soyez.', 'auth-register.webp'),
  ];
  Future<void> finish(String path) async {
    await ref.read(sessionStoreProvider).markOnboarding();
    if (mounted) context.go(path);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.carbon,
    body: SafeArea(
      child: Column(
        children: [
          const Padding(
            padding: EdgeInsets.all(24),
            child: BrandLogo(dark: true),
          ),
          Expanded(
            child: PageView(
              onPageChanged: (v) => setState(() => page = v),
              children: pages
                  .map(
                    (p) => Column(
                      children: [
                        Expanded(
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 24),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(
                                AppRadius.card,
                              ),
                              child: SizedBox(
                                width: double.infinity,
                                child: SafePhoto(
                                  '',
                                  asset: 'assets/images/${p.$2}',
                                ),
                              ),
                            ),
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.all(24),
                          child: Text(
                            p.$1,
                            style: AppTypography.title.copyWith(
                              color: AppColors.ivory,
                            ),
                            textAlign: TextAlign.center,
                          ),
                        ),
                      ],
                    ),
                  )
                  .toList(),
            ),
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(
              3,
              (i) => Container(
                width: i == page ? 24 : 8,
                height: 8,
                margin: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: i == page ? AppColors.orange : AppColors.muted,
                  borderRadius: BorderRadius.circular(5),
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                FilledButton(
                  onPressed: () => finish('/home'),
                  child: const Text('Commencer'),
                ),
                TextButton(
                  onPressed: () => finish('/login'),
                  child: const Text(
                    'Connexion',
                    style: TextStyle(color: AppColors.ivory),
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
