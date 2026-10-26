// lib/providers/audit_providers.dart
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/audit_models.dart';
import '../models/paginated.dart';
import 'auth_provider.dart';

/// Filter + page for the read-only fines list. A record, so two equal
/// queries share one cached result.
typedef AuditFineQuery = ({String status, String search, int page});

/// Filter + page for the findings list.
typedef AuditFindingQuery = ({String status, int page});

/// Filter + page for the activity log. An empty action means all actions.
typedef AuditLogQuery = ({String action, int page});

/// GET /api/v1/officer/audit/summary — needs the View Reports permission;
/// a 403 just means this officer doesn't have it, handled in the screen.
final auditSummaryProvider = FutureProvider.autoDispose<AuditSummary>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/officer/audit/summary');
  return AuditSummary.fromJson(res['data'] as Map<String, dynamic>);
});

/// GET /api/v1/officer/audit/discrepancies — recomputed by the server on
/// every call, most severe first.
final auditDiscrepanciesProvider =
    FutureProvider.autoDispose<List<AuditDiscrepancy>>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/officer/audit/discrepancies');
  final list = res['data'] as List<dynamic>? ?? [];
  return list.map((e) => AuditDiscrepancy.fromJson(e as Map<String, dynamic>)).toList();
});

/// GET /api/v1/officer/audit/fines?status=&q=&page= — read-only.
final auditFinesProvider = FutureProvider.autoDispose
    .family<Paginated<AuditFineRow>, AuditFineQuery>((ref, query) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/officer/audit/fines', query: {
    if (query.status.isNotEmpty) 'status': query.status,
    if (query.search.isNotEmpty) 'q': query.search,
    'page': query.page,
  });
  return Paginated.fromJson(res['data'] as Map<String, dynamic>, AuditFineRow.fromJson);
});

/// GET /api/v1/officer/audit/attendance?page= — session totals (paged) plus
/// the flagged-scan history and manual overrides. Read-only.
final auditAttendanceProvider = FutureProvider.autoDispose.family<AuditAttendance, int>((ref, page) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/officer/audit/attendance', query: {'page': page});
  return AuditAttendance.fromJson(res['data'] as Map<String, dynamic>);
});

/// GET /api/v1/officer/audit/activity-log?action=&page= — read-only; the
/// server only ever sends the masked IP.
final auditActivityLogProvider = FutureProvider.autoDispose
    .family<AuditActivityLog, AuditLogQuery>((ref, query) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/officer/audit/activity-log', query: {
    if (query.action.isNotEmpty) 'action': query.action,
    'page': query.page,
  });
  return AuditActivityLog.fromJson(res['data'] as Map<String, dynamic>);
});

/// GET /api/v1/officer/audit/findings?status=&page=
final auditFindingsProvider = FutureProvider.autoDispose
    .family<Paginated<AuditFinding>, AuditFindingQuery>((ref, query) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/officer/audit/findings', query: {
    if (query.status.isNotEmpty) 'status': query.status,
    'page': query.page,
  });
  return Paginated.fromJson(res['data'] as Map<String, dynamic>, AuditFinding.fromJson);
});
