class Fine {
  const Fine({
    required this.id,
    required this.userId,
    this.studentName,
    this.studentIdNumber,
    required this.violationType,
    required this.reason,
    required this.amount,
    required this.status, // unpaid | paid | waived
    required this.issuedAt,
    this.eventTitle,
    this.sessionType,
  });

  final int id;
  final int userId;
  final String? studentName;
  final String? studentIdNumber;
  final String violationType;
  final String reason;
  final double amount;
  final String status;
  final String issuedAt;
  final String? eventTitle;
  final String? sessionType;

  bool get isUnpaid => status == 'unpaid';
  bool get isPaid => status == 'paid';
  bool get isWaived => status == 'waived';

  factory Fine.fromJson(Map<String, dynamic> json) {
    final user = json['user'] as Map<String, dynamic>?;
    final event = json['event'] as Map<String, dynamic>?;
    final session = json['event_session'] as Map<String, dynamic>?;
    return Fine(
      id: json['id'] as int,
      userId: json['user_id'] as int,
      studentName: user?['name'] as String?,
      studentIdNumber: user?['student_id'] as String?,
      violationType: json['violation_type'] as String? ?? '',
      reason: json['reason'] as String? ?? '',
      amount: double.tryParse('${json['amount']}') ?? 0,
      status: json['status'] as String? ?? 'unpaid',
      issuedAt: json['issued_at'] as String? ?? '',
      eventTitle: event?['title'] as String?,
      sessionType: session?['session_type'] as String?,
    );
  }
}
