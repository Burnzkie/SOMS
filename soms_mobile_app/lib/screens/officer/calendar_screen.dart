import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:table_calendar/table_calendar.dart';

import '../../providers/officer_providers.dart';
import '../../widgets/status_views.dart';

class OfficerCalendarScreen extends ConsumerStatefulWidget {
  const OfficerCalendarScreen({super.key});

  @override
  ConsumerState<OfficerCalendarScreen> createState() =>
      _OfficerCalendarScreenState();
}

class _OfficerCalendarScreenState extends ConsumerState<OfficerCalendarScreen> {
  DateTime _focusedDay = DateTime.now();
  DateTime? _selectedDay;

  @override
  Widget build(BuildContext context) {
    final feed = ref.watch(officerCalendarProvider);

    return feed.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => ErrorRetryView(
          message: '$e',
          onRetry: () => ref.invalidate(officerCalendarProvider)),
      data: (items) {
        final eventsByDay = _groupByDay(items);
        final selected = _selectedDay ?? _focusedDay;
        final dayItems = eventsByDay[_dateKey(selected)] ?? const [];

        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(officerCalendarProvider),
          child: ListView(
            children: [
              TableCalendar(
                firstDay: DateTime.utc(2020, 1, 1),
                lastDay: DateTime.utc(2035, 12, 31),
                focusedDay: _focusedDay,
                selectedDayPredicate: (day) => isSameDay(_selectedDay, day),
                eventLoader: (day) => eventsByDay[_dateKey(day)] ?? const [],
                onDaySelected: (selectedDay, focusedDay) {
                  setState(() {
                    _selectedDay = selectedDay;
                    _focusedDay = focusedDay;
                  });
                },
                onPageChanged: (focusedDay) => _focusedDay = focusedDay,
                calendarStyle: const CalendarStyle(markersMaxCount: 4),
              ),
              const Divider(height: 1),
              const Padding(
                padding: EdgeInsets.fromLTRB(16, 12, 16, 4),
                child: Row(
                  children: [
                    _LegendDot(color: Color(0xFF5B5BF6)),
                    SizedBox(width: 6),
                    Text('SOMS Events', style: TextStyle(fontSize: 12)),
                    SizedBox(width: 16),
                    _LegendDot(color: Color(0xFF8C90A3)),
                    SizedBox(width: 6),
                    Text('Custom Entries', style: TextStyle(fontSize: 12)),
                  ],
                ),
              ),
              if (dayItems.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(24),
                  child: Center(child: Text('Nothing on this day.')),
                )
              else
                for (final item in dayItems)
                  ListTile(
                    leading: Icon(Icons.circle,
                        size: 12, color: _parseColor(item['color'] as String?)),
                    title: Text(item['title'] as String? ?? ''),
                  ),
            ],
          ),
        );
      },
    );
  }

  /// Buckets each feed item into every calendar day it spans (multi-day
  /// SOMS events use "start"/"end"; custom entries are single-day and
  /// only have "start").
  Map<String, List<Map<String, dynamic>>> _groupByDay(
      List<Map<String, dynamic>> items) {
    final map = <String, List<Map<String, dynamic>>>{};
    for (final item in items) {
      final start = DateTime.tryParse(item['start'] as String? ?? '');
      if (start == null) continue;
      final endRaw = item['end'] as String?;
      // FullCalendar's "end" is exclusive (see Officer\CalendarController::buildEventFeed) —
      // subtract a day so the last visually-included day is used as the loop bound.
      final end = endRaw != null
          ? (DateTime.tryParse(endRaw)?.subtract(const Duration(days: 1)) ??
              start)
          : start;

      for (var d = start; !d.isAfter(end); d = d.add(const Duration(days: 1))) {
        map.putIfAbsent(_dateKey(d), () => []).add(item);
      }
    }
    return map;
  }

  String _dateKey(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  Color _parseColor(String? hex) {
    if (hex == null || !hex.startsWith('#')) return Colors.grey;
    final value = int.tryParse(hex.substring(1), radix: 16);
    if (value == null) return Colors.grey;
    return Color(0xFF000000 | value);
  }
}

class _LegendDot extends StatelessWidget {
  const _LegendDot({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) => Container(
      width: 9,
      height: 9,
      decoration: BoxDecoration(color: color, shape: BoxShape.circle));
}
