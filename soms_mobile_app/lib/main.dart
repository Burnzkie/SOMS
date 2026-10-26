// lib/main.dart
import 'package:flutter/foundation.dart' show kReleaseMode;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sentry_flutter/sentry_flutter.dart';

import 'core/api_config.dart';
import 'core/app_theme.dart';
import 'providers/auth_provider.dart';
import 'screens/admin/admin_home_screen.dart';
import 'screens/auth/change_password_screen.dart';
import 'screens/auth/login_screen.dart';
import 'screens/officer/officer_home_screen.dart';
import 'screens/student/student_home_screen.dart';

Future<void> main() async {
  // Deploy-readiness fix (Sep 2026): fail loudly at startup if a release
  // build forgot --dart-define=API_BASE_URL, instead of installing fine
  // and then silently failing every API call. See ApiConfig for details.
  ApiConfig.assertConfiguredForRelease();

  // Error tracking (Sep 2026). SENTRY_DSN comes from --dart-define at
  // build time (same mechanism as API_BASE_URL above), not from a
  // committed file -- an empty DSN makes the SDK a safe no-op rather
  // than an error, so this doesn't break local runs without one
  // configured. See CHANGES.md for where to get a DSN and the exact
  // --dart-define flag to add to your existing build/run commands.
  //
  // sendDefaultPii is explicitly false to match config/sentry.php on
  // the Laravel side: SOMS handles student records under RA 10173, so
  // device identifiers and IP address are never attached to error
  // reports. Sentry.configureScope's user id is likewise never set
  // anywhere in this app.
  await SentryFlutter.init(
    (options) {
      options.dsn = const String.fromEnvironment('SENTRY_DSN');
      options.tracesSampleRate = 0.2;
      options.sendDefaultPii = false;
      options.attachScreenshot = false;
      options.environment = kReleaseMode ? 'production' : 'development';
    },
    appRunner: () => runApp(const ProviderScope(child: SomsApp())),
  );
}

// Light-first design shared with the web app. All colors, radii and component styles live in
// core/app_theme.dart (copied from the web layout's CSS variables), so a change there updates
// every screen. Only the dark theme (unused: themeMode is light) is still defined here.
class SomsApp extends StatelessWidget {
  const SomsApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SOMS',
      debugShowCheckedModeBanner: false,
      themeMode: ThemeMode.light,
      darkTheme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(
            seedColor: AppColors.primary, brightness: Brightness.dark),
        scaffoldBackgroundColor: const Color(0xFF0A0B10),
        inputDecorationTheme:
            const InputDecorationTheme(border: OutlineInputBorder()),
      ),
      theme: AppTheme.light(),
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
