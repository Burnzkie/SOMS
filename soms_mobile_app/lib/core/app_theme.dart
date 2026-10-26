// lib/core/app_theme.dart
import 'package:flutter/material.dart';

/// Design tokens copied from the web layout's CSS variables
/// (resources/views/layouts/app.blade.php :root), so web and mobile share one palette.
/// Change a color here and every screen that uses the theme follows.
class AppColors {
  AppColors._();

  // Brand
  static const primary = Color(0xFF1D4ED8); // --primary
  static const primaryLight =
      Color(0xFF3B82F6); // --violet (second stop of the primary gradient)
  static const navy =
      Color(0xFF0B1F4B); // --bg-elevated: sidebar / app bar / auth backdrop
  static const navyLight = Color(0xFF0E2657);
  static const navyDark = Color(0xFF081840);

  // Surfaces
  static const background = Color(0xFFEEF2F9); // --bg
  static const surface = Colors.white; // --surface
  static const surface2 = Color(0xFFF5F7FC); // --surface-2 (input fill)
  // rgba(15,23,42,.07) / .13 flattened onto white, so Flutter borders stay solid colors.
  static const border = Color(0xFFEEEFF0);
  static const borderStrong = Color(0xFFE0E1E3);

  // Text
  static const text = Color(0xFF0F172A);
  static const textMuted = Color(0xFF64748B);
  static const textFaint = Color(0xFF94A3B8);

  // Status accents (the *Text variants are the WCAG-AA-safe versions for text on white)
  static const emerald = Color(0xFF10B981);
  static const emeraldText = Color(0xFF0B7C56);
  static const amber = Color(0xFFF59E0B);
  static const amberText = Color(0xFF996206);
  static const rose = Color(0xFFF43F5E);
  static const roseText = Color(0xFFD00C2E);
  static const purple = Color(0xFF8B5CF6);
}

class AppTheme {
  AppTheme._();

  static const double radiusMd = 12; // --radius-md
  static const double radiusLg = 18; // --radius-lg

  static ThemeData light() {
    final scheme = ColorScheme.fromSeed(
            seedColor: AppColors.primary, brightness: Brightness.light)
        .copyWith(
      primary: AppColors.primary,
      onPrimary: Colors.white,
      secondary: AppColors.emerald,
      tertiary: AppColors.amber,
      error: AppColors.roseText,
      surface: AppColors.surface,
      onSurface: AppColors.text,
      onSurfaceVariant: AppColors.textMuted,
      outline: AppColors.textFaint,
      outlineVariant: AppColors.borderStrong,
      surfaceContainerHighest: const Color(0xFFE6EBF5),
    );

    final inputBorder = OutlineInputBorder(
      borderRadius: BorderRadius.circular(radiusMd),
      borderSide: const BorderSide(color: AppColors.borderStrong),
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: AppColors.background,
      fontFamily: 'Roboto',
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.navy,
        foregroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: TextStyle(
            color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700),
        iconTheme: IconThemeData(color: Colors.white),
      ),
      cardTheme: CardThemeData(
        color: AppColors.surface,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusLg),
          side: const BorderSide(color: AppColors.border),
        ),
        margin: const EdgeInsets.symmetric(vertical: 6),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: AppColors.primary.withValues(alpha: .08),
        labelStyle: const TextStyle(
            color: AppColors.primary,
            fontWeight: FontWeight.w600,
            fontSize: 12),
        side: BorderSide.none,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
          minimumSize: const Size(0, 46),
          padding: const EdgeInsets.symmetric(horizontal: 18),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(radiusMd)),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
          elevation: 0,
          minimumSize: const Size(0, 42),
          padding: const EdgeInsets.symmetric(horizontal: 18),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(radiusMd)),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.primary,
          minimumSize: const Size(0, 46),
          side: const BorderSide(color: AppColors.borderStrong),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(radiusMd)),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.primary,
          textStyle: const TextStyle(fontWeight: FontWeight.w600),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 3,
        indicatorColor: AppColors.primary.withValues(alpha: .12),
        labelTextStyle: WidgetStateProperty.resolveWith((states) => TextStyle(
              fontSize: 11,
              fontWeight: states.contains(WidgetState.selected)
                  ? FontWeight.w700
                  : FontWeight.w500,
              color: states.contains(WidgetState.selected)
                  ? AppColors.primary
                  : AppColors.textMuted,
            )),
        iconTheme: WidgetStateProperty.resolveWith((states) => IconThemeData(
              color: states.contains(WidgetState.selected)
                  ? AppColors.primary
                  : AppColors.textMuted,
            )),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surface2,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        labelStyle: const TextStyle(color: AppColors.textMuted, fontSize: 14),
        hintStyle: const TextStyle(color: AppColors.textFaint),
        border: inputBorder,
        enabledBorder: inputBorder,
        focusedBorder: inputBorder.copyWith(
            borderSide: const BorderSide(color: AppColors.primary, width: 1.6)),
        errorBorder: inputBorder.copyWith(
            borderSide: const BorderSide(color: AppColors.roseText)),
        focusedErrorBorder: inputBorder.copyWith(
            borderSide:
                const BorderSide(color: AppColors.roseText, width: 1.6)),
        errorStyle: const TextStyle(color: AppColors.roseText, fontSize: 12),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radiusLg)),
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
            borderRadius:
                BorderRadius.vertical(top: Radius.circular(radiusLg))),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: AppColors.navy,
        contentTextStyle: const TextStyle(color: Colors.white),
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radiusMd)),
      ),
      popupMenuTheme: PopupMenuThemeData(
        color: Colors.white,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radiusMd)),
      ),
      dividerTheme: const DividerThemeData(color: AppColors.border, space: 1),
    );
  }
}
