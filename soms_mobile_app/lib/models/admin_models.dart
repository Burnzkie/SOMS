/// One row of the Officer Appointment panel — a position, who (if anyone)
/// currently holds it, and whether it's vacant. See
/// Api\Admin\OfficerAppointmentController::index.
class OfficerPanelRow {
  const OfficerPanelRow({
    required this.position,
    this.positionId,
    required this.vacant,
    this.officerId,
    this.officerName,
    this.officerStudentId,
  });

  final String position;
  final int? positionId; // officer_positions row id -- needed for revoke
  final bool vacant;
  final int? officerId;
  final String? officerName;
  final String? officerStudentId;

  factory OfficerPanelRow.fromJson(Map<String, dynamic> json) {
    final officer = json['officer'] as Map<String, dynamic>?;
    return OfficerPanelRow(
      position: json['position'] as String? ?? '',
      positionId: json['position_id'] as int?,
      vacant: json['vacant'] as bool? ?? true,
      officerId: officer?['id'] as int?,
      officerName: officer?['name'] as String?,
      officerStudentId: officer?['student_id'] as String?,
    );
  }
}

/// An approved student, selectable in the appoint form.
class ApprovedStudent {
  const ApprovedStudent({required this.id, required this.name, required this.studentId});

  final int id;
  final String name;
  final String studentId;

  factory ApprovedStudent.fromJson(Map<String, dynamic> json) => ApprovedStudent(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        studentId: json['student_id'] as String? ?? '',
      );
}

/// GET /api/v1/admin/users row — full User model as returned by
/// Admin\UserController::index. Deliberately separate from AppUser
/// (the login-response model), which only carries the minimal auth
/// fields — this needs is_approved/department for the approvals list.
class AdminUserRow {
  const AdminUserRow({
    required this.id,
    required this.name,
    required this.studentId,
    required this.role,
    this.department,
    required this.isApproved,
    required this.createdAt,
  });

  final int id;
  final String name;
  final String studentId;
  final String role;
  final String? department;
  final bool isApproved;
  final String createdAt;

  factory AdminUserRow.fromJson(Map<String, dynamic> json) => AdminUserRow(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        studentId: json['student_id'] as String? ?? '',
        role: json['role'] as String? ?? 'student',
        department: json['department'] as String?,
        isApproved: json['is_approved'] as bool? ?? false,
        createdAt: json['created_at'] as String? ?? '',
      );
}

class ActivityLogEntry {
  const ActivityLogEntry({
    required this.id,
    required this.action,
    this.userName,
    required this.createdAt,
  });

  final int id;
  final String action;
  final String? userName;
  final String createdAt;

  factory ActivityLogEntry.fromJson(Map<String, dynamic> json) {
    final user = json['user'] as Map<String, dynamic>?;
    return ActivityLogEntry(
      id: json['id'] as int,
      action: json['action'] as String? ?? '',
      userName: user?['name'] as String?,
      createdAt: json['created_at'] as String? ?? '',
    );
  }
}

/// GET /api/v1/admin/system-health — see Admin\SystemHealthController.
class SystemHealth {
  const SystemHealth({
    required this.queueStale,
    required this.logChainOk,
    required this.treasurerActive,
    this.lastFineJobRunAt,
  });

  final bool queueStale;
  final bool logChainOk;
  final bool treasurerActive;
  final String? lastFineJobRunAt;

  factory SystemHealth.fromJson(Map<String, dynamic> json) => SystemHealth(
        queueStale: json['queueStale'] as bool? ?? true,
        logChainOk: json['logChainOk'] as bool? ?? false,
        treasurerActive: json['treasurerActive'] as bool? ?? false,
        lastFineJobRunAt: json['lastFineJobRunAt'] as String?,
      );
}

/// GET /api/v1/admin/reports — see Api\Admin\ReportController. Only the
/// aggregate totals are modeled here; the paid/waived line-item lists
/// reuse the Fine model directly.
class FineReportSummary {
  const FineReportSummary({required this.totalCollected, required this.totalWaived});

  final double totalCollected;
  final double totalWaived;

  factory FineReportSummary.fromJson(Map<String, dynamic> json) => FineReportSummary(
        totalCollected: double.tryParse('${json['totalCollected']}') ?? 0,
        totalWaived: double.tryParse('${json['totalWaived']}') ?? 0,
      );
}
