import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/admin_models.dart';
import '../models/fine.dart';
import '../models/paginated.dart';
import 'auth_provider.dart';

/// [status] filters to pending/approved; null fetches all. [search] filters
/// by student_id or name (matches Admin\UserController::index server-side).
final adminUsersProvider =
    FutureProvider.autoDispose.family<Paginated<AdminUserRow>, ({String? status, String? search})>((ref, filter) async {
  final api = ref.watch(apiClientProvider);
  final query = <String, dynamic>{};
  if (filter.status != null) query['status'] = filter.status;
  if (filter.search != null && filter.search!.isNotEmpty) query['search'] = filter.search;
  final res = await api.get('/admin/users', query: query.isEmpty ? null : query);
  return Paginated.fromJson(res['data'] as Map<String, dynamic>, AdminUserRow.fromJson);
});

final userActivityLogProvider =
    FutureProvider.autoDispose.family<Paginated<ActivityLogEntry>, int>((ref, userId) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/admin/users/$userId/activity-log');
  return Paginated.fromJson(res['data'] as Map<String, dynamic>, ActivityLogEntry.fromJson);
});

/// { panel: List<OfficerPanelRow>, approvedStudents: List<ApprovedStudent>, academicYear: String }
final officerAppointmentPanelProvider =
    FutureProvider.autoDispose<(List<OfficerPanelRow>, List<ApprovedStudent>, String)>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/admin/officers');
  final data = res['data'] as Map<String, dynamic>;
  final panel = (data['panel'] as List<dynamic>? ?? [])
      .map((e) => OfficerPanelRow.fromJson(e as Map<String, dynamic>))
      .toList();
  final students = (data['approvedStudents'] as List<dynamic>? ?? [])
      .map((e) => ApprovedStudent.fromJson(e as Map<String, dynamic>))
      .toList();
  return (panel, students, data['academicYear'] as String? ?? '');
});

final activityLogsProvider = FutureProvider.autoDispose<(Paginated<ActivityLogEntry>, bool)>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/admin/activity-logs');
  final data = res['data'] as Map<String, dynamic>;
  final logs = Paginated.fromJson(data['logs'] as Map<String, dynamic>, ActivityLogEntry.fromJson);
  return (logs, data['logChainOk'] as bool? ?? false);
});

final systemHealthProvider = FutureProvider.autoDispose<SystemHealth>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/admin/system-health');
  return SystemHealth.fromJson(res['data'] as Map<String, dynamic>);
});

/// { paid: List<Fine>, waived: List<Fine>, totalCollected, totalWaived }
final fineReportProvider = FutureProvider.autoDispose<(List<Fine>, List<Fine>, FineReportSummary)>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/admin/reports');
  final data = res['data'] as Map<String, dynamic>;
  final paid = (data['paid'] as List<dynamic>? ?? []).map((e) => Fine.fromJson(e as Map<String, dynamic>)).toList();
  final waived =
      (data['waived'] as List<dynamic>? ?? []).map((e) => Fine.fromJson(e as Map<String, dynamic>)).toList();
  return (paid, waived, FineReportSummary.fromJson(data));
});
