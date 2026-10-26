// lib/core/api_config.dart

/// Central place for the one thing every environment differs on: where the
/// Laravel API lives.
///
/// Defaults to the deployed Render API, so a plain `flutter run` or
/// `flutter build apk --release` talks to production. Override for local
/// testing with:
///
///   flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
///
/// Use your laptop's hotspot/LAN IP instead of 10.0.2.2 on a physical device.
class ApiConfig {
  // Deployed Render API (HTTPS, so no cleartext manifest flag needed).
  static const String _renderDefault = 'https://soms-zlyx.onrender.com/api/v1';

  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: _renderDefault,
  );
}
