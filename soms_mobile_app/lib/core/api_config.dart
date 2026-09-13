import 'package:flutter/foundation.dart' show kReleaseMode;

/// Central place for the one thing every environment differs on: where the
/// Laravel API lives. Override at build/run time, e.g.:
///
///   flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
///   flutter build apk --release --dart-define=API_BASE_URL=https://soms.onrender.com/api/v1
///
/// Defaults to the Android emulator's loopback alias for `php artisan serve`
/// on localhost:8000. Use your machine's LAN IP instead for a physical device.
class ApiConfig {
  static const String _emulatorDefault = 'http://10.0.2.2:8000/api/v1';

  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: _emulatorDefault,
  );

  /// Deploy-readiness fix (Sep 2026): nothing previously stopped a release
  /// build from shipping with the emulator-loopback default still baked
  /// in -- it would install and run fine, then fail every API call with
  /// no obvious cause. Call this once from main() so a --release build
  /// forgetting --dart-define=API_BASE_URL fails loudly at startup
  /// instead of silently in production.
  static void assertConfiguredForRelease() {
    if (kReleaseMode && baseUrl == _emulatorDefault) {
      throw StateError(
        'ApiConfig.baseUrl is still the emulator default ($_emulatorDefault) '
        'in a release build. Rebuild with:\n'
        '  flutter build apk --release --dart-define=API_BASE_URL=<production API URL>',
      );
    }
  }
}
