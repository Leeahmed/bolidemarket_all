import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../shared/widgets.dart';

class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});
  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(start);
  }

  Future<void> start() async {
    await ref.read(authProvider.notifier).restore();
    if (!mounted || ref.read(authProvider).hasError) return;
    context.go(
      ref.read(authProvider.notifier).onboarding ? '/onboarding' : '/home',
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    return Scaffold(
      backgroundColor: AppColors.carbon,
      body: Center(
        child: auth.hasError
            ? Theme(
                data: Theme.of(context).copyWith(
                  colorScheme: Theme.of(
                    context,
                  ).colorScheme.copyWith(onSurface: AppColors.ivory),
                ),
                child: ErrorPanel(auth.error!, start),
              )
            : TweenAnimationBuilder<double>(
                tween: Tween(begin: .96, end: 1),
                duration: MediaQuery.disableAnimationsOf(context)
                    ? Duration.zero
                    : const Duration(milliseconds: 350),
                builder: (_, value, child) =>
                    Transform.scale(scale: value, child: child),
                child: Image.asset('assets/images/symbol.png', width: 180),
              ),
      ),
    );
  }
}
