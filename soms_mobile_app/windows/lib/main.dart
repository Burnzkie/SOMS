import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'providers/auth_provider.dart';
import 'screens/admin/admin_home_screen.dart';
import 'screens/auth/change_password_screen.dart';
import 'screens/auth/login_screen.dart';
import 'screens/officer/officer_home_screen.dart';
import 'screens/student/student_home_screen.dart';

void main() {
  runApp(const ProviderScope(child: SomsApp()));
}

// Dark Modern / Vercel-style canonical direction — violet primary, dark
// theme by default (matches the web app.blade.php token system).
const _primary = Color(0xFF5B5BF6);

class SomsApp extends StatelessWidget {
  const SomsApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SOMS',
      debugShowCheckedModeBanner: false,
      themeMode: ThemeMode.dark,
      darkTheme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(seedColor: _primary, brightness: Brightness.dark),
        scaffoldBackgroundColor: const Color(0xFF0A0B10),
        inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder()),
      ),
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(seedColor: _primary, brightness: Brightness.light),
        inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder()),
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
