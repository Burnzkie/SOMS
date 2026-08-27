class EventFineRule {
  const EventFineRule({required this.violationType, required this.amount});

  final String violationType;
  final double amount;

  factory EventFineRule.fromJson(Map<String, dynamic> json) => EventFineRule(
        violationType: json['violation_type'] as String? ?? '',
        amount: double.tryParse('${json['amount']}') ?? 0,
      );
}

class EventSession {
  const EventSession({
    required this.id,
    required this.sessionType,
    required this.timeinStart,
    required this.timeinEnd,
    this.timeoutStart,
    this.timeoutEnd,
    required this.finesIssued,
  });

  final int id;
  final String sessionType; // morning | afternoon | parade
  final String timeinStart;
  final String timeinEnd;
  final String? timeoutStart;
  final String? timeoutEnd;
  final bool finesIssued;

  factory EventSession.fromJson(Map<String, dynamic> json) => EventSession(
        id: json['id'] as int,
        sessionType: json['session_type'] as String? ?? '',
        timeinStart: json['timein_start'] as String? ?? '',
        timeinEnd: json['timein_end'] as String? ?? '',
        timeoutStart: json['timeout_start'] as String?,
        timeoutEnd: json['timeout_end'] as String?,
        finesIssued: json['fines_issued'] as bool? ?? false,
      );
}

class EventDay {
  const EventDay({required this.id, required this.date, this.label, required this.sessions});

  final int id;
  final String date;
  final String? label;
  final List<EventSession> sessions;

  factory EventDay.fromJson(Map<String, dynamic> json) => EventDay(
        id: json['id'] as int,
        date: json['date'] as String? ?? '',
        label: json['label'] as String?,
        sessions: (json['sessions'] as List<dynamic>? ?? [])
            .map((e) => EventSession.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class SomsEvent {
  const SomsEvent({
    required this.id,
    required this.title,
    this.description,
    this.venue,
    required this.type,
    required this.dateStart,
    required this.dateEnd,
    required this.hasParade,
    required this.isPublished,
    this.eventDays = const [],
    this.fineRules = const [],
  });

  final int id;
  final String title;
  final String? description;
  final String? venue;
  final String type; // foundation_day | other
  final String dateStart;
  final String dateEnd;
  final bool hasParade;
  final bool isPublished;
  final List<EventDay> eventDays;
  final List<EventFineRule> fineRules;

  factory SomsEvent.fromJson(Map<String, dynamic> json) => SomsEvent(
        id: json['id'] as int,
        title: json['title'] as String? ?? '',
        description: json['description'] as String?,
        venue: json['venue'] as String?,
        type: json['type'] as String? ?? 'other',
        dateStart: json['date_start'] as String? ?? '',
        dateEnd: json['date_end'] as String? ?? '',
        hasParade: json['has_parade'] as bool? ?? false,
        isPublished: json['is_published'] as bool? ?? false,
        eventDays: (json['event_days'] as List<dynamic>? ?? [])
            .map((e) => EventDay.fromJson(e as Map<String, dynamic>))
            .toList(),
        fineRules: (json['fine_rules'] as List<dynamic>? ?? [])
            .map((e) => EventFineRule.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

/// A student's own attendance record against one event — see
/// StudentApiController::eventShow's "my_attendance" array.
class MyAttendanceRecord {
  const MyAttendanceRecord({
    required this.eventSessionId,
    required this.scanType,
    required this.status,
    this.scannedAt,
  });

  final int eventSessionId;
  final String scanType;
  final String status;
  final String? scannedAt;

  factory MyAttendanceRecord.fromJson(Map<String, dynamic> json) => MyAttendanceRecord(
        eventSessionId: json['event_session_id'] as int,
        scanType: json['scan_type'] as String? ?? '',
        status: json['status'] as String? ?? '',
        scannedAt: json['scanned_at'] as String?,
      );
}
