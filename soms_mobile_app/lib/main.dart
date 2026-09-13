import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/api_config.dart';
import 'providers/auth_provider.dart';
import 'screens/admin/admin_home_screen.dart';
import 'screens/auth/change_password_screen.dart';
import 'screens/auth/login_screen.dart';
import 'screens/officer/officer_home_screen.dart';
import 'screens/student/student_home_screen.dart';

void main() {
  // Deploy-readiness fix (Sep 2026): fail loudly at startup if a release
  // build forgot --dart-define=API_BASE_URL, instead of installing fine
  // and then silently failing every API call. See ApiConfig for details.
  ApiConfig.assertConfiguredForRelease();
  runApp(const ProviderScope(child: SomsApp()));
}

// "Lively" light-first direction (Sept 2026 redesign) — vivid blue primary,
// light content by default with a dark navy accent reserved for app bars/
// nav chrome, replacing the earlier all-dark "Dark Modern" system. Web's
// layouts/app.blade.php got the equivalent token swap in the same pass —
// see CHANGELOG for the visual-language rationale.
const _primary = Color(0xFF2563EB);

class SomsApp extends StatelessWidget {
  const SomsApp({super.key});

  @override
  Widget build(BuildContext context) {
    final lightScheme = ColorScheme.fromSeed(seedColor: _primary, brightness: Brightness.light).copyWith(
      secondary: const Color(0xFF10B981),
      tertiary: const Color(0xFFF59E0B),
      surface: Colors.white,
    );

    return MaterialApp(
      title: 'SOMS',
      debugShowCheckedModeBanner: false,
      themeMode: ThemeMode.light,
      darkTheme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(seedColor: _primary, brightness: Brightness.dark),
        scaffoldBackgroundColor: const Color(0xFF0A0B10),
        inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder()),
      ),
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: lightScheme,
        scaffoldBackgroundColor: const Color(0xFFF4F6FB),
        fontFamily: 'Roboto',
        appBarTheme: AppBarTheme(
          backgroundColor: const Color(0xFF0B1330),
          foregroundColor: Colors.white,
          elevation: 0,
          centerTitle: false,
          titleTextStyle: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700),
          iconTheme: const IconThemeData(color: Colors.white),
        ),
        cardTheme: CardThemeData(
          color: Colors.white,
          elevation: 0,
          surfaceTintColor: Colors.transparent,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
            side: BorderSide(color: lightScheme.outlineVariant.withValues(alpha: .6)),
          ),
          margin: const EdgeInsets.symmetric(vertical: 6),
        ),
        chipTheme: ChipThemeData(
          backgroundColor: lightScheme.primary.withValues(alpha: .08),
          labelStyle: TextStyle(color: lightScheme.primary, fontWeight: FontWeight.w600, fontSize: 12),
          side: BorderSide.none,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
        ),
        filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
          ),
        ),
        navigationBarTheme: NavigationBarThemeData(
          backgroundColor: Colors.white,
          indicatorColor: lightScheme.primary.withValues(alpha: .12),
          surfaceTintColor: Colors.transparent,
          elevation: 2,
        ),
        inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: const Color(0xFFF4F6FB),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: lightScheme.outlineVariant),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: lightScheme.outlineVariant),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: lightScheme.primary, width: 1.6),
          ),
        ),
      ),
      home: const AuthGate(),
    );
  }
}

/// Watches AuthController's state and shows the right screen — this is the
/// whole "router" for now. If the app grows past 3-4 top-level states,
/// swap this for go_router; not worth the dependency yet.
class AuthGate extends ConsumerWidget {
  const AuthGate({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authProvider);

    switch (auth.status) {
      case AuthStatus.bootstrapping:
        return const Scaffold(body: Center(child: CircularProgressIndicator()));

      case AuthStatus.unauthenticated:
        return const LoginScreen();

      case AuthStatus.needsPasswordChange:
        return const ChangePasswordScreen();

      case AuthStatus.authenticated:
        final role = auth.user?.role ?? 'student';
        switch (role) {
          case 'admin':
            return const AdminHomeScreen();
          case 'officer':
            return const OfficerHomeScreen();
          default:
            return const StudentHomeScreen();
        }
    }
  }
}
