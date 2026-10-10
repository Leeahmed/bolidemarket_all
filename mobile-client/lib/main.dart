import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'core/api.dart';
import 'core/router.dart';
import 'core/theme.dart';
import 'features/realtime/gate.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  AppConfig.validate();
  runApp(const ProviderScope(child: BolideMarketApp()));
}

class BolideMarketApp extends ConsumerWidget {
  const BolideMarketApp({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => MaterialApp.router(
    title: 'BolideMarket',
    debugShowCheckedModeBanner: false,
    scaffoldMessengerKey: appMessengerKey,
    builder: (_, child) => RealtimeGate(child: child!),
    theme: appTheme(),
    locale: const Locale('fr'),
    supportedLocales: const [Locale('fr')],
    localizationsDelegates: GlobalMaterialLocalizations.delegates,
    routerConfig: ref.watch(routerProvider),
  );
}
