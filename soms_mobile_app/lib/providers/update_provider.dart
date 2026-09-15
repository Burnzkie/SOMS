// lib/providers/update_provider.dart
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../services/update_service.dart';
import 'auth_provider.dart';

/// Same wiring pattern as academicProgramsProvider — depends on the app's
/// one shared ApiClient instance rather than constructing its own Dio.
final updateServiceProvider = Provider<UpdateService>((ref) {
  final api = ref.watch(apiClientProvider);
  return UpdateService(api);
});
