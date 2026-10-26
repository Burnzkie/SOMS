import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/admin_models.dart';
import '../models/audit_models.dart';
import '../models/fine.dart';
import '../models/paginated.dart';
import 'audit_providers.dart' show AuditFindingQuery;
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

/// { panel: List<OfficerPanelRow>, approvedStudents: List<ApprovedStudent>,
///   academicYear: String, availablePermissions: Map<key, label> }
final officerAppointmentPanelProvider = FutureProvider.autoDispose<
    (List<OfficerPanelRow>, List<ApprovedStudent>, String, Map<String, String>)>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/admin/officers');
  final data = res['data'] as Map<String, dynamic>;
  final panel = (data['panel'] as List<dynamic>? ?? [])
      .map((e) => OfficerPanelRow.fromJson(e as Map<String, dynamic>))
      .toList();
  final students = (data['approvedStudents'] as List<dynamic>? ?? [])
      .map((e) => ApprovedStudent.fromJson(e as Map<String, dynamic>))
      .toList();
  final availablePermissions = (data['availablePermissions'] as Map<String, dynamic>? ?? {})
      .map((k, v) => MapEntry(k, v.toString()));
  return (panel, students, data['academicYear'] as String? ?? '', availablePermissions);
});

/// GET /api/v1/admin/audit-findings -> (the page of findings, how many still
/// need action). The Admin acknowledges and resolves what the Auditor reported.
final adminFindingsProvider = FutureProvider.autoDispose
    .family<(Paginated<AuditFinding>, int), AuditFindingQuery>((ref, query) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/admin/audit-findings', query: {
    if (query.status.isNotEmpty) 'status': query.status,
    'page': query.page,
  });
  final page = Paginated.fromJson(res['data'] as Map<String, dynamic>, AuditFinding.fromJson);
  final meta = res['meta'] as Map<String, dynamic>? ?? {};
  return (page, (meta['active_count'] as num?)?.toInt() ?? 0);
});

/// Recommended permissions per position title, from GET /admin/officers
/// (permissionPresets). Only fetched when the Admin taps "Apply recommended".
final permissionPresetsProvider = FutureProvider.autoDispose<Map<String, List<String>>>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/admin/officers');
  final data = res['data'] as Map<String, dynamic>;
  final raw = data['permissionPresets'] as Map<String, dynamic>? ?? {};
  return raw.map((title, keys) => MapEntry(title, (keys as List<dynamic>).map((e) => '$e').toList()));
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
