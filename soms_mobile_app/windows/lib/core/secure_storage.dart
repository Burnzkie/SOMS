import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Thin wrapper around FlutterSecureStorage. Everything here — the Sanctum
/// token, the cached user blob, and (later) the offline scan queue — is
/// treated with the same sensitivity, per 03-Auth-Security.md: never
/// SharedPreferences.
class SecureStorage {
  SecureStorage._();
  static final SecureStorage instance = SecureStorage._();

  final FlutterSecureStorage _storage = const FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static const _tokenKey = 'soms_auth_token';
  static const _userKey = 'soms_user_json';
  static const _offlineQueueKey = 'offline_scan_queue';

  Future<void> saveToken(String token) => _storage.write(key: _tokenKey, value: token);
  Future<String?> readToken() => _storage.read(key: _tokenKey);
  Future<void> deleteToken() => _storage.delete(key: _tokenKey);

  Future<void> saveUserJson(String json) => _storage.write(key: _userKey, value: json);
  Future<String?> readUserJson() => _storage.read(key: _userKey);
  Future<void> deleteUserJson() => _storage.delete(key: _userKey);

  Future<void> clearAll() async {
    await deleteToken();
    await deleteUserJson();
  }

  // Offline scan queue — see 10-Mobile-Deployment.md Part C. Kept as raw
  // JSON string here; QueuedScan (de)serialization lives in its own model
  // (models/queued_scan.dart), read/written by ScanQueueController
  // (providers/scan_providers.dart).
  Future<String?> readOfflineQueue() => _storage.read(key: _offlineQueueKey);
  Future<void> writeOfflineQueue(String json) => _storage.write(key: _offlineQueueKey, value: json);
  Future<void> clearOfflineQueue() => _storage.delete(key: _offlineQueueKey);
}
