import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/announcement.dart';
import '../models/event.dart';
import '../models/fine.dart';
import '../models/paginated.dart';
import '../models/qr_data.dart';
import 'auth_provider.dart';

/// GET /api/v1/student/dashboard
final studentDashboardProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/student/dashboard');
  return res['data'] as Map<String, dynamic>? ?? {};
});

/// GET /api/v1/student/qr — re-fetch on demand (pull to refresh / timer),
/// never cached long-term since the token rotates roughly every 60s.
final studentQrProvider = FutureProvider.autoDispose<QrData>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/student/qr');
  return QrData.fromJson(res['data'] as Map<String, dynamic>);
});

final studentEventsProvider = FutureProvider.autoDispose<Paginated<SomsEvent>>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/student/events');
  return Paginated.fromJson(res['data'] as Map<String, dynamic>, SomsEvent.fromJson);
});

final studentEventDetailProvider =
    FutureProvider.autoDispose.family<(SomsEvent, List<MyAttendanceRecord>), int>((ref, eventId) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/student/events/$eventId');
  final data = res['data'] as Map<String, dynamic>;
  final event = SomsEvent.fromJson(data['event'] as Map<String, dynamic>);
  final attendance = (data['my_attendance'] as List<dynamic>? ?? [])
      .map((e) => MyAttendanceRecord.fromJson(e as Map<String, dynamic>))
      .toList();
  return (event, attendance);
});

/// [status] filters to unpaid/paid/waived; null fetches all.
final studentFinesProvider = FutureProvider.autoDispose.family<Paginated<Fine>, String?>((ref, status) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/student/fines', query: status != null ? {'status': status} : null);
  return Paginated.fromJson(res['data'] as Map<String, dynamic>, Fine.fromJson);
});

final studentAnnouncementsProvider = FutureProvider.autoDispose<List<SomsAnnouncement>>((ref) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/student/announcements');
  final list = res['data'] as List<dynamic>? ?? [];
  return list.map((e) => SomsAnnouncement.fromJson(e as Map<String, dynamic>)).toList();
});

final studentAnnouncementDetailProvider =
    FutureProvider.autoDispose.family<SomsAnnouncement, int>((ref, id) async {
  final api = ref.watch(apiClientProvider);
  final res = await api.get('/student/announcements/$id');
  return SomsAnnouncement.fromJson(res['data'] as Map<String, dynamic>);
});
