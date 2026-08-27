/// Central place for the one thing every environment differs on: where the
/// Laravel API lives. Override at build/run time, e.g.:
///
///   flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
///   flutter build apk --dart-define=API_BASE_URL=https://soms.onrender.com/api/v1
///
/// Defaults to the Android emulator's loopback alias for `php artisan serve`
/// on localhost:8000. Use your machine's LAN IP instead for a physical device.
class ApiConfig {
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );
}
