/// One scan captured while offline, sitting in SecureStorage until the
/// device is back online and it can be sent through
/// POST /api/v1/officer/attendance/scan-batch (AttendanceController::scanBatch).
///
/// `localId` is generated on-device purely to let the queue UI and the
/// sync result matching work (dedupe, "this one failed" feedback) — it
/// never leaves the device and has no meaning to the backend.
class QueuedScan {
  QueuedScan({
    required this.localId,
    required this.sessionId,
    required this.userId,
    required this.token,
    required this.deviceScannedAt,
    this.studentNameHint,
    this.eventTitleHint,
  });

  final String localId;
  final int sessionId;
  final int userId;
  final String token;
  final DateTime deviceScannedAt;

  /// Display-only, never sent to the server — filled in from the scan
  /// screen's already-loaded session/event context so the pending-queue
  /// list can show something readable while offline.
  final String? studentNameHint;
  final String? eventTitleHint;

  Map<String, dynamic> toJson() => {
        'local_id': localId,
        'session_id': sessionId,
        'user_id': userId,
        'token': token,
        'device_scanned_at': deviceScannedAt.toIso8601String(),
        'student_name_hint': studentNameHint,
        'event_title_hint': eventTitleHint,
      };

  factory QueuedScan.fromJson(Map<String, dynamic> json) => QueuedScan(
        localId: json['local_id'] as String,
        sessionId: json['session_id'] as int,
        userId: json['user_id'] as int,
        token: json['token'] as String,
        deviceScannedAt: DateTime.parse(json['device_scanned_at'] as String),
        studentNameHint: json['student_name_hint'] as String?,
        eventTitleHint: json['event_title_hint'] as String?,
      );

  /// Exactly the shape scanBatch expects for one `scans[]` item.
  Map<String, dynamic> toScanBatchItem() => {
        'session_id': sessionId,
        'user_id': userId,
        'token': token,
        'device_scanned_at': deviceScannedAt.toIso8601String(),
      };
}
