import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';
import '../core/secure_storage.dart';
import '../models/user.dart';

enum AuthStatus {
  bootstrapping,
  unauthenticated,
  needsPasswordChange,
  authenticated
}

class AuthState {
  const AuthState({required this.status, this.user, this.errorMessage});

  final AuthStatus status;
  final AppUser? user;
  final String? errorMessage;

  AuthState copyWith(
          {AuthStatus? status, AppUser? user, String? errorMessage}) =>
      AuthState(
        status: status ?? this.status,
        user: user ?? this.user,
        errorMessage: errorMessage,
      );

  static const bootstrapping = AuthState(status: AuthStatus.bootstrapping);
}

/// apiClientProvider has no knowledge of authProvider — one-directional
/// dependency only. See the doc comment on ApiClient.onUnauthorized for why.
final apiClientProvider = Provider<ApiClient>((ref) => ApiClient());

final authProvider = StateNotifierProvider<AuthController, AuthState>((ref) {
  final api = ref.watch(apiClientProvider);
  final controller = AuthController(api);
  // Wired here, after both objects exist, instead of inside apiClientProvider's
  // own builder — that's what avoids the circular type-inference error.
  api.onUnauthorized = controller.forceLogout;
  return controller;
});

class AuthController extends StateNotifier<AuthState> {
  AuthController(this._api) : super(AuthState.bootstrapping) {
    _bootstrap();
  }

  final ApiClient _api;

  /// On app start: if a token + cached user are present, restore the
  /// session optimistically (still role/must_change_password gated below).
  /// A stale/expired token simply surfaces as a 401 on the first real
  /// request, which routes back through forceLogout().
  Future<void> _bootstrap() async {
    final token = await SecureStorage.instance.readToken();
    final userJson = await SecureStorage.instance.readUserJson();

    if (token == null || userJson == null) {
      state = const AuthState(status: AuthStatus.unauthenticated);
      return;
    }

    final user = AppUser.fromJson(jsonDecode(userJson) as Map<String, dynamic>);
    state = AuthState(
      status: user.mustChangePassword
          ? AuthStatus.needsPasswordChange
          : AuthStatus.authenticated,
      user: user,
    );
  }

  /// POST /api/v1/auth/login — see 10-Mobile-Deployment.md Part B.
  Future<void> login(String studentId, String password) async {
    state =
        state.copyWith(status: AuthStatus.unauthenticated, errorMessage: null);
    try {
      final res = await _api.post('/auth/login', data: {
        'student_id': studentId,
        'password': password,
      });

      final data = res['data'] as Map<String, dynamic>? ?? res;
      final token = data['token'] as String;
      final user = AppUser.fromJson(data['user'] as Map<String, dynamic>);

      await SecureStorage.instance.saveToken(token);
      await SecureStorage.instance.saveUserJson(jsonEncode(user.toJson()));

      state = AuthState(
        status: user.mustChangePassword
            ? AuthStatus.needsPasswordChange
            : AuthStatus.authenticated,
        user: user,
      );
    } on ApiException catch (e) {
      state = AuthState(
          status: AuthStatus.unauthenticated, errorMessage: e.message);
      rethrow;
    }
  }

  /// POST /api/v1/auth/register — student self-registration. No token is
  /// issued (see RegisterController): the account is created with
  /// is_approved=false and must wait on an Admin to approve it, so this
  /// deliberately does not touch AuthState — the caller just shows a
  /// "pending approval" message and returns to the login screen.
  Future<void> register({
    required String name,
    required String studentId,
    required String email,
    required String department,
    required String program,
    required String level,
  }) async {
    await _api.post('/auth/register', data: {
      'name': name,
      'student_id': studentId,
      'email': email,
      'department': department,
      'program': program,
      'level': level,
    });
  }

  /// POST /api/v1/auth/change-password — required before any dashboard
  /// route is reachable when must_change_password is true.
  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {
    await _api.post('/auth/change-password', data: {
      'current_password': currentPassword,
      'password': password,
      'password_confirmation': passwordConfirmation,
    });

    // Server revokes all Sanctum tokens on password change
    // (03-Auth-Security.md) — the old token is now dead, so this always
    // ends in a fresh login rather than silently continuing the session.
    await SecureStorage.instance.clearAll();
    state = const AuthState(status: AuthStatus.unauthenticated);
  }

  Future<void> logout() async {
    try {
      await _api.post('/auth/logout');
    } on ApiException {
      // Best-effort — clear local session regardless of server reachability.
    }
    await SecureStorage.instance.clearAll();
    state = const AuthState(status: AuthStatus.unauthenticated);
  }

  /// Called by ApiClient's onUnauthorized interceptor on any 401 —
  /// clears local session without attempting another server round-trip
  /// (the token is already invalid server-side).
  Future<void> forceLogout() async {
    await SecureStorage.instance.clearAll();
    state = const AuthState(
        status: AuthStatus.unauthenticated,
        errorMessage: 'Session expired. Please log in again.');
  }
}
