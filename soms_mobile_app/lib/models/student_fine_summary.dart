// lib/models/student_fine_summary.dart
// (adjust the folder to wherever fine.dart lives in your project)

import 'fine.dart';

/// One student's overall fines, as returned by GET /officer/fines/students.
/// Lets the treasurer see what a student owes and settle it in one tap.
class StudentFineSummary {
  const StudentFineSummary({
    required this.userId,
    required this.name,
    required this.studentId,
    required this.unpaidTotal,
    required this.unpaidCount,
    required this.paidCount,
    required this.waivedCount,
    required this.fines,
  });

  final int userId;
  final String name;
  final String studentId;
  final double unpaidTotal;
  final int unpaidCount;
  final int paidCount;
  final int waivedCount;

  /// Every fine of this student (for the expandable breakdown / per-fine actions).
  final List<Fine> fines;

  /// Drives whether "Mark all paid" / "Waive all" are shown.
  bool get hasUnpaid => unpaidCount > 0;

  factory StudentFineSummary.fromJson(Map<String, dynamic> json) {
    final rawFines = json['fines'] as List<dynamic>? ?? [];
    return StudentFineSummary(
      userId: json['user_id'] as int,
      name: json['name'] as String? ?? '',
      studentId: json['student_id'] as String? ?? '',
      unpaidTotal: double.tryParse('${json['unpaid_total']}') ?? 0,
      unpaidCount: (json['unpaid_count'] as num?)?.toInt() ?? 0,
      paidCount: (json['paid_count'] as num?)?.toInt() ?? 0,
      waivedCount: (json['waived_count'] as num?)?.toInt() ?? 0,
      fines: rawFines
          .map((f) => Fine.fromJson(f as Map<String, dynamic>))
          .toList(),
    );
  }
}

/// Response of POST /officer/fines/student/{id}/clear and /waive.
class BulkSettleResult {
  const BulkSettleResult({
    required this.message,
    required this.settledCount,
    required this.settledTotal,
  });

  final String message;
  final int settledCount;
  final double settledTotal;

  factory BulkSettleResult.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>? ?? {};
    return BulkSettleResult(
      message: json['message'] as String? ?? '',
      settledCount: (data['settled_count'] as num?)?.toInt() ?? 0,
      settledTotal: double.tryParse('${data['settled_total']}') ?? 0,
    );
  }
}
