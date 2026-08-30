import 'package:dio/dio.dart';

import 'api_config.dart';
import 'secure_storage.dart';

/// Thrown for any non-2xx API response, carrying Laravel's
/// { success:false, message, errors:{field:[msg]} } shape (10-Mobile-Deployment.md).
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.errors});

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;

  /// True on 401 — callers use this to force a logout / redirect to login.
  bool get isUnauthorized => statusCode == 401;

  @override
  String toString() => message;
}

/// One Dio instance for the whole app. Attaches the Sanctum Bearer token
/// (Flutter Secure Storage, never SharedPreferences — 03-Auth-Security.md)
/// to every request, and normalizes error responses into ApiException.
///
/// `onUnauthorized` is a mutable field (set by the auth provider *after*
/// construction) rather than a constructor parameter. Riverpod's
/// apiClientProvider and authProvider each depend on the other's provider
/// if this is wired at construction time, which is a genuine circular
/// type-inference error in Dart even though it would work at runtime.
/// Making it a settable field breaks the cycle: apiClientProvider no
/// longer needs to know about authProvider at all.
class ApiClient {
  ApiClient() {
    _dio = Dio(BaseOptions(
      baseUrl: ApiConfig.baseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 15),
      headers: {'Accept': 'application/json'},
    ));

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await SecureStorage.instance.readToken();
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (DioException e, handler) {
        if (e.response?.statusCode == 401) {
          onUnauthorized?.call();
        }
        handler.next(e);
      },
    ));
  }

  late final Dio _dio;

  /// Set by AuthController once both providers exist — see auth_provider.dart.
  void Function()? onUnauthorized;

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _request(() => _dio.get(path, queryParameters: query));

  Future<Map<String, dynamic>> post(String path, {Map<String, dynamic>? data}) =>
      _request(() => _dio.post(path, data: data));

  Future<Map<String, dynamic>> put(String path, {Map<String, dynamic>? data}) =>
      _request(() => _dio.put(path, data: data));

  /// Multipart file upload — same token/error handling as everything else
  /// above, just a different Dio call underneath since FormData isn't a
  /// Map<String, dynamic>. [method] is 'POST' or 'PUT'; used by avatar
  /// upload (create vs replace).
  Future<Map<String, dynamic>> postMultipart(
    String path,
    FormData formData, {
    String method = 'POST',
  }) =>
      _request(() => method == 'PUT'
          ? _dio.put(path, data: formData)
          : _dio.post(path, data: formData));

  Future<Map<String, dynamic>> delete(String path) => _request(() => _dio.delete(path));

  Future<Map<String, dynamic>> _request(Future<Response> Function() call) async {
    try {
      final res = await call();
      final body = res.data;
      if (body is Map<String, dynamic>) return body;
      return {'success': true, 'data': body};
    } on DioException catch (e) {
      final data = e.response?.data;
      if (data is Map<String, dynamic>) {
        throw ApiException(
          (data['message'] as String?) ?? 'Something went wrong.',
          statusCode: e.response?.statusCode,
          errors: data['errors'] as Map<String, dynamic>?,
        );
      }
      throw ApiException(
        e.type == DioExceptionType.connectionError || e.type == DioExceptionType.connectionTimeout
            ? 'Can\'t reach the server. Check your connection.'
            : 'Something went wrong.',
        statusCode: e.response?.statusCode,
      );
    }
  }
}