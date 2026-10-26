// lib/models/flagged_scan.dart

/// One offline scan held for review because the officer's device clock was
/// more than 10 minutes off from the server when it synced.
/// From GET /api/v1/officer/attendance/sessions/{id}/flagged.
/// The student is NOT fined while a scan is in this state.
class FlaggedScan {
  const FlaggedScan({
    required this.attendanceId,
    required this.studentName,
    required this.studentId,
    required this.scanType,
    this.deviceScannedAt,
    this.serverReceivedAt,
  });

  final int attendanceId;
  final String studentName;
  final String studentId;
  final String scanType; // time_in | time_out
  final DateTime? deviceScannedAt;
  final DateTime? serverReceivedAt;

  bool get isTimeOut => scanType == 'time_out';

  factory FlaggedScan.fromJson(Map<String, dynamic> json) => FlaggedScan(
        attendanceId: json['attendance_id'] as int,
        studentName: json['student_name'] as String? ?? 'Unknown student',
        studentId: json['student_id'] as String? ?? '',
        scanType: json['scan_type'] as String? ?? 'time_in',
        deviceScannedAt: DateTime.tryParse(json['device_scanned_at'] as String? ?? '')?.toLocal(),
        serverReceivedAt: DateTime.tryParse(json['server_received_at'] as String? ?? '')?.toLocal(),
      );
}
