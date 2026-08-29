// lib/providers/academic_programs_provider.dart
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'auth_provider.dart';

/// GET /api/v1/academic-programs — public endpoint mirroring
/// config/academic_programs.php on the backend. This is the same list the
/// web registration form's cascading dropdowns use, so department/program
/// options never drift out of sync between platforms.
///
/// { "Computer Studies": ["BS Information Technology (BSIT)", ...], ... }
final academicProgramsProvider =
    FutureProvider<Map<String, List<String>>>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/academic-programs');
  final data = res['data'] as Map<String, dynamic>;
  return data.map((department, programs) =>
      MapEntry(department, (programs as List).cast<String>()));
});
