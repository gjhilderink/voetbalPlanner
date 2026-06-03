import 'package:flutter/material.dart';

class AppColors {
  static const primary = Color(0xFF16a34a);
  static const primaryDark = Color(0xFF15803d);
  static const primaryLight = Color(0xFFdcfce7);
  static const textPrimary = Color(0xFF1a1a1a);
  static const textMuted = Color(0xFF6b7280);
  static const border = Color(0xFFe5e7eb);
  static const background = Color(0xFFf9fafb);
  static const white = Color(0xFFffffff);
  static const danger = Color(0xFFb91c1c);
  static const warning = Color(0xFFa16207);

  static const shiftOchtend = Color(0xFF1d4ed8);
  static const shiftMiddag = Color(0xFFa16207);
  static const shiftAvond = Color(0xFF7e22ce);

  static const statusOpen = Color(0xFFea580c);
  static const statusBevestigd = Color(0xFF1d4ed8);
  static const statusVervuld = Color(0xFF15803d);
}

final ThemeData appTheme = ThemeData(
  colorScheme: ColorScheme.fromSeed(
    seedColor: AppColors.primary,
    primary: AppColors.primary,
    surface: AppColors.background,
  ),
  scaffoldBackgroundColor: AppColors.background,
  appBarTheme: const AppBarTheme(
    backgroundColor: AppColors.primary,
    foregroundColor: AppColors.white,
    elevation: 0,
  ),
  elevatedButtonTheme: ElevatedButtonThemeData(
    style: ElevatedButton.styleFrom(
      backgroundColor: AppColors.primary,
      foregroundColor: AppColors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      padding: const EdgeInsets.symmetric(vertical: 14),
    ),
  ),
  inputDecorationTheme: InputDecorationTheme(
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(8),
      borderSide: const BorderSide(color: AppColors.border),
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(8),
      borderSide: const BorderSide(color: AppColors.border),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(8),
      borderSide: const BorderSide(color: AppColors.primary, width: 2),
    ),
    filled: true,
    fillColor: AppColors.white,
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
  ),
  cardTheme: CardThemeData(
    color: AppColors.white,
    elevation: 0,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(8),
      side: const BorderSide(color: AppColors.border),
    ),
  ),
  chipTheme: ChipThemeData(
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
  ),
  progressIndicatorTheme: const ProgressIndicatorThemeData(
    color: AppColors.primary,
  ),
  fontFamily: 'Roboto',
  useMaterial3: true,
);
