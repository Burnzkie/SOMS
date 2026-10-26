// lib/models/audit_models.dart
import 'paginated.dart';

/// What the signed-in officer may open in the Audit workspace, worked out
/// from the permission keys the server reports (dashboard-summary). Each
/// flag matches the permission the server checks for that tab.
class AuditAccess {
  const AuditAccess({
    required this.canViewReports,
    required this.canViewFines,
    required this.canManageFindings,
    required this.canViewAttendance,
    required this.canViewLogs,
  });

  factory AuditAccess.fromPermissions(Set<String> permissions) => AuditAccess(
        canViewReports: permissions.contains('view_reports'),
        canViewFines: permissions.contains('view_fines'),
        canManageFindings: permissions.contains('manage_audit_findings'),
        canViewAttendance: permissions.contains('view_attendance'),
        canViewLogs: permissions.contains('view_audit_logs'),
      );

  final bool canViewReports; // overview + discrepancy report
  final bool canViewFines; // read-only fines list
  final bool canManageFindings; // record findings
  final bool canViewAttendance; // read-only attendance records
  final bool canViewLogs; // read-only activity log

  /// Findings may be read with either permission (same rule as the server).
  bool get canViewFindings => canViewReports || canManageFindings;

  /// Anything in the Audit workspace at all. An officer with only
  /// view_attendance (the Secretary, say) still opens it, to see attendance.
  bool get any => canViewReports || canViewFines || canManageFindings || canViewAttendance || canViewLogs;
}

int _int(dynamic value) => (value as num?)?.toInt() ?? 0;
double _double(dynamic value) => (value as num?)?.toDouble() ?? 0;
DateTime? _date(dynamic value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;

/// GET /api/v1/officer/audit/summary
class AuditSummary {
  const AuditSummary({
    required this.assessedCount,
    required this.assessedTotal,
    required this.collectedCount,
    required this.collectedTotal,
    required this.waivedCount,
    required this.waivedTotal,
    required this.outstandingCount,
    required this.outstandingTotal,
    required this.pendingFlagged,
    required this.activeFindings,
  });

  final int assessedCount;
  final double assessedTotal;
  final int collectedCount;
  final double collectedTotal;
  final int waivedCount;
  final double waivedTotal;
  final int outstandingCount;
  final double outstandingTotal;
  final int pendingFlagged;
  final int activeFindings;

  factory AuditSummary.fromJson(Map<String, dynamic> json) => AuditSummary(
        assessedCount: _int(json['assessed_count']),
        assessedTotal: _double(json['assessed_total']),
        collectedCount: _int(json['collected_count']),
        collectedTotal: _double(json['collected_total']),
        waivedCount: _int(json['waived_count']),
        waivedTotal: _double(json['waived_total']),
        outstandingCount: _int(json['outstanding_count']),
        outstandingTotal: _double(json['outstanding_total']),
        pendingFlagged: _int(json['pending_flagged']),
        activeFindings: _int(json['active_findings']),
      );
}

/// One problem found by the server's checks (GET .../audit/discrepancies).
class AuditDiscrepancy {
  const AuditDiscrepancy({
    required this.check,
    required this.checkLabel,
    required this.severity,
    required this.referenceType,
    required this.referenceId,
    required this.student,
    required this.summary,
    this.event,
  });

  final String check;
  final String checkLabel;
  final String severity; // high | medium | low
  final String referenceType; // fine | attendance | session
  final int referenceId;
  final String student;
  final String? event;
  final String summary;

  factory AuditDiscrepancy.fromJson(Map<String, dynamic> json) => AuditDiscrepancy(
        check: json['check'] as String? ?? '',
        checkLabel: json['check_label'] as String? ?? (json['check'] as String? ?? ''),
        severity: json['severity'] as String? ?? 'low',
        referenceType: json['reference_type'] as String? ?? 'general',
        referenceId: _int(json['reference_id']),
        student: json['student'] as String? ?? '—',
        event: json['event'] as String?,
        summary: json['summary'] as String? ?? '',
      );
}

/// One row of the read-only fines list (GET .../audit/fines).
class AuditFineRow {
  const AuditFineRow({
    required this.id,
    required this.studentName,
    required this.studentId,
    required this.violationType,
    required this.amount,
    required this.status,
    this.event,
    this.issuedAt,
    this.settledBy,
    this.settledAt,
    this.receiptNumber,
    this.reason,
  });

  final int id;
  final String studentName;
  final String studentId;
  final String? event;
  final String violationType;
  final double amount;
  final String status; // unpaid | paid | waived
  final DateTime? issuedAt;
  final String? settledBy;
  final DateTime? settledAt;
  final String? receiptNumber;
  final String? reason;

  factory AuditFineRow.fromJson(Map<String, dynamic> json) => AuditFineRow(
        id: _int(json['id']),
        studentName: json['student_name'] as String? ?? 'Unknown student',
        studentId: json['student_id'] as String? ?? '',
        event: json['event'] as String?,
        violationType: json['violation_type'] as String? ?? '',
        amount: _double(json['amount']),
        status: json['status'] as String? ?? 'unpaid',
        issuedAt: _date(json['issued_at']),
        settledBy: json['settled_by'] as String?,
        settledAt: _date(json['settled_at']),
        receiptNumber: json['receipt_number'] as String?,
        reason: json['reason'] as String?,
      );
}

/// One session's attendance totals (GET .../audit/attendance, sessions).
class AuditSessionRow {
  const AuditSessionRow({
    required this.sessionId,
    required this.event,
    required this.session,
    required this.present,
    required this.pendingFlagged,
    required this.approved,
    required this.rejected,
    required this.overrides,
    required this.fines,
    required this.finesIssued,
    this.timeinStart,
    this.timeinEnd,
  });

  final int sessionId;
  final String event;
  final String session;
  final DateTime? timeinStart;
  final DateTime? timeinEnd;
  final int present;
  final int pendingFlagged;
  final int approved;
  final int rejected;
  final int overrides;
  final int fines;
  final bool finesIssued;

  factory AuditSessionRow.fromJson(Map<String, dynamic> json) => AuditSessionRow(
        sessionId: _int(json['session_id']),
        event: json['event'] as String? ?? '',
        session: json['session'] as String? ?? '',
        timeinStart: _date(json['timein_start']),
        timeinEnd: _date(json['timein_end']),
        present: _int(json['present']),
        pendingFlagged: _int(json['pending_flagged']),
        approved: _int(json['approved']),
        rejected: _int(json['rejected']),
        overrides: _int(json['overrides']),
        fines: _int(json['fines']),
        finesIssued: json['fines_issued'] as bool? ?? false,
      );
}

/// An offline scan flagged for clock drift, and what was decided about it.
class AuditFlaggedScan {
  const AuditFlaggedScan({
    required this.attendanceId,
    required this.studentName,
    required this.studentId,
    required this.event,
    required this.session,
    required this.scanType,
    this.deviceScannedAt,
    this.scannedAt,
    this.decision,
    this.reviewedBy,
    this.reviewedAt,
  });

  final int attendanceId;
  final String studentName;
  final String studentId;
  final String event;
  final String session;
  final String scanType; // time_in | time_out
  final DateTime? deviceScannedAt;
  final DateTime? scannedAt;
  final String? decision; // approved | rejected | null while pending
  final String? reviewedBy;
  final DateTime? reviewedAt;

  factory AuditFlaggedScan.fromJson(Map<String, dynamic> json) => AuditFlaggedScan(
        attendanceId: _int(json['attendance_id']),
        studentName: json['student_name'] as String? ?? '',
        studentId: json['student_id'] as String? ?? '',
        event: json['event'] as String? ?? '',
        session: json['session'] as String? ?? '',
        scanType: json['scan_type'] as String? ?? 'time_in',
        deviceScannedAt: _date(json['device_scanned_at']),
        scannedAt: _date(json['scanned_at']),
        decision: json['decision'] as String?,
        reviewedBy: json['reviewed_by'] as String?,
        reviewedAt: _date(json['reviewed_at']),
      );
}

/// Attendance an officer marked by hand.
class AuditOverride {
  const AuditOverride({
    required this.attendanceId,
    required this.studentName,
    required this.studentId,
    required this.event,
    required this.session,
    required this.scanType,
    this.markedBy,
    this.scannedAt,
    this.reason,
  });

  final int attendanceId;
  final String studentName;
  final String studentId;
  final String event;
  final String session;
  final String scanType;
  final String? markedBy;
  final DateTime? scannedAt;
  final String? reason;

  factory AuditOverride.fromJson(Map<String, dynamic> json) => AuditOverride(
        attendanceId: _int(json['attendance_id']),
        studentName: json['student_name'] as String? ?? '',
        studentId: json['student_id'] as String? ?? '',
        event: json['event'] as String? ?? '',
        session: json['session'] as String? ?? '',
        scanType: json['scan_type'] as String? ?? 'time_in',
        markedBy: json['marked_by'] as String?,
        scannedAt: _date(json['scanned_at']),
        reason: json['reason'] as String?,
      );
}

/// Everything GET .../audit/attendance returns.
class AuditAttendance {
  const AuditAttendance({required this.sessions, required this.flaggedHistory, required this.overrides});

  final Paginated<AuditSessionRow> sessions;
  final List<AuditFlaggedScan> flaggedHistory;
  final List<AuditOverride> overrides;

  factory AuditAttendance.fromJson(Map<String, dynamic> json) => AuditAttendance(
        sessions: Paginated.fromJson(json['sessions'] as Map<String, dynamic>, AuditSessionRow.fromJson),
        flaggedHistory: (json['flagged_history'] as List<dynamic>? ?? [])
            .map((e) => AuditFlaggedScan.fromJson(e as Map<String, dynamic>))
            .toList(),
        overrides: (json['overrides'] as List<dynamic>? ?? [])
            .map((e) => AuditOverride.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

/// One activity-log entry. [ip] is the masked address; the real one is never sent.
class AuditLogRow {
  const AuditLogRow({
    required this.id,
    required this.user,
    required this.action,
    this.createdAt,
    this.record,
    this.details,
    this.ip,
  });

  final int id;
  final DateTime? createdAt;
  final String user;
  final String action;
  final String? record;
  final String? details;
  final String? ip;

  factory AuditLogRow.fromJson(Map<String, dynamic> json) => AuditLogRow(
        id: _int(json['id']),
        createdAt: _date(json['created_at']),
        user: json['user'] as String? ?? 'System',
        action: json['action'] as String? ?? '',
        record: json['record'] as String?,
        details: json['details'] as String?,
        ip: json['ip'] as String?,
      );
}

/// Everything GET .../audit/activity-log returns.
class AuditActivityLog {
  const AuditActivityLog({required this.logs, required this.actions, required this.chainOk});

  final Paginated<AuditLogRow> logs;
  final List<String> actions;
  final bool chainOk;

  factory AuditActivityLog.fromJson(Map<String, dynamic> json) => AuditActivityLog(
        logs: Paginated.fromJson(json['logs'] as Map<String, dynamic>, AuditLogRow.fromJson),
        actions: (json['actions'] as List<dynamic>? ?? []).map((e) => '$e').toList(),
        chainOk: json['chain_ok'] as bool? ?? false,
      );
}

/// An audit finding (GET/POST .../audit/findings).
class AuditFinding {
  const AuditFinding({
    required this.id,
    required this.referenceType,
    required this.title,
    required this.issue,
    required this.severity,
    required this.status,
    this.referenceId,
    this.evidence,
    this.reportedAt,
    this.reporterName,
    this.resolutionNote,
    this.resolverName,
    this.resolvedAt,
  });

  final int id;
  final String referenceType;
  final int? referenceId;
  final String title;
  final String issue;
  final String? evidence;
  final String severity;
  final String status; // open | acknowledged | resolved | dismissed
  final DateTime? reportedAt;
  final String? reporterName;
  final String? resolutionNote;
  final String? resolverName;
  final DateTime? resolvedAt;

  factory AuditFinding.fromJson(Map<String, dynamic> json) => AuditFinding(
        id: _int(json['id']),
        referenceType: json['reference_type'] as String? ?? 'general',
        referenceId: json['reference_id'] == null ? null : _int(json['reference_id']),
        title: json['title'] as String? ?? '',
        issue: json['issue'] as String? ?? '',
        evidence: json['evidence'] as String?,
        severity: json['severity'] as String? ?? 'medium',
        status: json['status'] as String? ?? 'open',
        reportedAt: _date(json['reported_at']),
        reporterName: (json['reporter'] as Map<String, dynamic>?)?['name'] as String?,
        resolutionNote: json['resolution_note'] as String?,
        resolverName: (json['resolver'] as Map<String, dynamic>?)?['name'] as String?,
        resolvedAt: _date(json['resolved_at']),
      );
}

/// Pre-filled values when a finding is raised from a discrepancy.
class AuditFindingDraft {
  const AuditFindingDraft({
    required this.referenceType,
    required this.referenceId,
    required this.title,
    required this.issue,
    required this.severity,
  });

  final String referenceType;
  final int referenceId;
  final String title;
  final String issue;
  final String severity;
}

const auditReferenceTypes = ['fine', 'attendance', 'session', 'general'];
const auditSeverities = ['low', 'medium', 'high'];
