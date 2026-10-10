import 'package:flutter/material.dart';

class AppColors {
  static const carbon = Color(0xFF0B0D0F),
      ivory = Color(0xFFF5F3EE),
      orange = Color(0xFFE85D2A),
      graphite = Color(0xFF25282D);
  static const muted = Color(0xFF73777E), border = Color(0xFFDDDEDF);
}

class AppSpacing {
  static const xs = 4.0, sm = 8.0, md = 16.0, lg = 24.0, xl = 32.0;
}

class AppRadius {
  static const small = 6.0, card = 12.0;
}

class AppShadows {
  static const card = [
    BoxShadow(color: Color(0x0F0B0D0F), offset: Offset(0, 4), blurRadius: 16),
  ];
}

class AppTypography {
  static const title = TextStyle(
    fontFamily: 'Sora',
    fontWeight: FontWeight.w700,
    fontSize: 28,
    height: 1.2,
  );
  static const heading = TextStyle(
    fontFamily: 'Sora',
    fontWeight: FontWeight.w600,
    fontSize: 22,
  );
  static const price = TextStyle(fontWeight: FontWeight.w700, fontSize: 20);
}

ThemeData appTheme() => ThemeData(
  useMaterial3: true,
  fontFamily: 'Inter',
  fontFamilyFallback: const ['Noto Color Emoji', 'Segoe UI Emoji'],
  scaffoldBackgroundColor: AppColors.ivory,
  colorScheme: ColorScheme.fromSeed(
    seedColor: AppColors.orange,
    primary: AppColors.orange,
    onPrimary: AppColors.carbon,
    surface: AppColors.ivory,
    onSurface: AppColors.carbon,
  ),
  appBarTheme: const AppBarTheme(
    backgroundColor: AppColors.ivory,
    foregroundColor: AppColors.carbon,
    surfaceTintColor: Colors.transparent,
  ),
  filledButtonTheme: FilledButtonThemeData(
    style: FilledButton.styleFrom(
      minimumSize: const Size(0, 50),
      textStyle: const TextStyle(
        fontFamily: 'Inter',
        fontWeight: FontWeight.w700,
      ),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.small),
      ),
    ),
  ),
  inputDecorationTheme: InputDecorationTheme(
    filled: true,
    fillColor: Colors.white,
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(AppRadius.small),
      borderSide: const BorderSide(color: AppColors.border),
    ),
  ),
  outlinedButtonTheme: OutlinedButtonThemeData(
    style: OutlinedButton.styleFrom(
      minimumSize: const Size(0, 50),
      foregroundColor: AppColors.carbon,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.small),
      ),
    ),
  ),
  textButtonTheme: TextButtonThemeData(
    style: TextButton.styleFrom(foregroundColor: AppColors.carbon),
  ),
  navigationBarTheme: NavigationBarThemeData(
    iconTheme: WidgetStateProperty.resolveWith(
      (states) => IconThemeData(
        color: states.contains(WidgetState.selected)
            ? AppColors.orange
            : AppColors.graphite,
      ),
    ),
    labelTextStyle: WidgetStateProperty.resolveWith(
      (states) => TextStyle(
        color: states.contains(WidgetState.selected)
            ? AppColors.orange
            : AppColors.muted,
        fontSize: 12,
      ),
    ),
    backgroundColor: Colors.white,
    indicatorColor: Color(0x22E85D2A),
  ),
);
