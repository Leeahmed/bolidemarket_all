import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'core/api.dart';
import 'core/router.dart';
import 'core/theme.dart';
import 'features/realtime/gate.dart';
import 'features/dev_preview/config.dart';
import 'features/dev_preview/providers.dart';
import 'features/dev_preview/gallery.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  AppConfig.validate();
  runApp(
    ProviderScope(
      overrides: visualDemoEnabled ? visualDemoOverrides() : const [],
      child: const BolideMarketApp(),
    ),
  );
}

class BolideMarketApp extends ConsumerWidget {
  const BolideMarketApp({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => MaterialApp.router(
    title: 'BolideMarket',
    debugShowCheckedModeBanner: false,
    scaffoldMessengerKey: appMessengerKey,
    builder: (_, child) => visualDemoEnabled
        ? VisualDemoFrame(child: RealtimeGate(child: child!))
        : RealtimeGate(child: child!),
    theme: appTheme(),
    locale: const Locale('fr'),
    supportedLocales: const [Locale('fr')],
    localizationsDelegates: GlobalMaterialLocalizations.delegates,
    routerConfig: ref.watch(routerProvider),
  );
}
