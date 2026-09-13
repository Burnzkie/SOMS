import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/announcement.dart';
import '../models/event.dart';
import '../models/fine.dart';
import '../models/paginated.dart';
import 'auth_provider.dart';

/// GET /api/v1/officer/dashboard-summary — currently just { active_members }.
/// See Api\Officer\DashboardController docblock for why this is separate
/// from the events/fines/announcements providers below.
final officerActiveMembersProvider = FutureProvider.autoDispose<int>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/officer/dashboard-summary');
  final data = res['data'] as Map<String, dynamic>? ?? {};
  return data['active_members'] as int? ?? 0;
});

final officerEventsProvider = FutureProvider.autoDispose<Paginated<SomsEvent>>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/officer/events');
  return Paginated.fromJson(res['data'] as Map<String, dynamic>, SomsEvent.fromJson);
});

final officerEventDetailProvider = FutureProvider.autoDispose.family<SomsEvent, int>((ref, eventId) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/officer/events/$eventId');
  return SomsEvent.fromJson(res['data'] as Map<String, dynamic>);
});

/// GET /api/v1/officer/calendar — same feed shape as the web
/// FullCalendar.js source. Each item: {id, title, start, end?, color, type}.
/// GET /api/v1/officer/attendance/sessions/{id}/delegates — Executive/
/// Administrative officers only (manage_attendance); a 403 here just
/// means this officer can't manage delegates, handled in the screen.
final sessionDelegatesProvider =
    FutureProvider.autoDispose.family<List<SessionDelegate>, int>((ref, sessionId) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/officer/attendance/sessions/$sessionId/delegates');
  final list = res['data'] as List<dynamic>? ?? [];
  return list.map((e) => SessionDelegate.fromJson(e as Map<String, dynamic>)).toList();
});

final officerCalendarProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/officer/calendar');
  final list = res['data'] as List<dynamic>? ?? [];
  return list.cast<Map<String, dynamic>>();
});

/// Fines module is Treasurer+Admin only server-side (FinePolicy) — a 403
/// here means "not your access," handled in the screen, not hidden
/// pre-emptively (login response carries role but not officer tier).
final officerFinesProvider = FutureProvider.autoDispose.family<Paginated<Fine>, String?>((ref, status) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/officer/fines', query: status != null ? {'status': status} : null);
  return Paginated.fromJson(res['data'] as Map<String, dynamic>, Fine.fromJson);
});

/// { announcements: Paginated<SomsAnnouncement>, canPublish: bool }
final officerAnnouncementsProvider =
    FutureProvider.autoDispose<(Paginated<SomsAnnouncement>, bool)>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/officer/announcements');
  final data = res['data'] as Map<String, dynamic>;
  final announcements = Paginated.fromJson(
    data['announcements'] as Map<String, dynamic>,
    SomsAnnouncement.fromJson,
  );
  return (announcements, data['canPublish'] as bool? ?? false);
});
