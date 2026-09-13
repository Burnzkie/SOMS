import 'dart:io';

import 'package:dio/dio.dart' show FormData, MultipartFile;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:table_calendar/table_calendar.dart';

import '../../core/api_client.dart';
import '../../providers/auth_provider.dart';
import '../../providers/officer_providers.dart';
import '../../widgets/status_views.dart';
import 'events_screen.dart' show OfficerEventDetailScreen;

/// Event type presets — must stay in sync with App\Support\EventTypes on
/// the backend (single source of truth there; this is the mobile mirror
/// since Dart can't share PHP enums).
const Map<String, ({String emoji, String label})> kEventTypePresets = {
  'student_assembly': (emoji: '🎉', label: 'Student Assembly'),
  'friendship_week': (emoji: '❤️', label: 'Friendship Week'),
  'community_service': (emoji: '🌱', label: 'Community Service'),
  'academic_activities': (emoji: '🎓', label: 'Academic Activities'),
  'talent_festival': (emoji: '🎤', label: 'Talent Festival'),
  'freshmen_welcome': (emoji: '☀️', label: 'Freshmen Welcome'),
  'week_of_prayer': (emoji: '🙏', label: 'Week of Prayer'),
  'intramurals': (emoji: '🏆', label: 'Intramurals'),
  'technology_week': (emoji: '💻', label: 'Technology Week'),
  'acquaintance_party': (emoji: '🎭', label: 'Acquaintance Party'),
  'foundation_day': (emoji: '🎉', label: 'Foundation Day'),
  'leadership_recognition': (emoji: '🏅', label: 'Leadership & Recognition'),
  'christmas_celebration': (emoji: '🎄', label: 'Christmas Celebration'),
  'other': (emoji: '📌', label: 'Other'),
};

const List<String> kFoundationDayActivities = [
  'Parade',
  'Booth Competition',
  'Sports',
  'Academic Competitions',
  'PAC Got Talent',
  'Amazing Race',
  'Mr. & Ms. Foundation Day',
  'Cultural Night',
  'Awarding Ceremony',
];

const List<Color> kEventColorPresets = [
  Color(0xFFFF7A29),
  Color(0xFF8B7CF6),
  Color(0xFF1FC98D),
  Color(0xFFF5A623),
  Color(0xFFF5497A),
  Color(0xFF3B9EFF),
];

String colorToHex(Color color) =>
    '#${color.toARGB32().toRadixString(16).substring(2).toUpperCase()}';

Color hexToColor(String? hex, {Color fallback = const Color(0xFFFF7A29)}) {
  if (hex == null || hex.isEmpty) return fallback;
  final cleaned = hex.replaceFirst('#', '');
  final value = int.tryParse(cleaned, radix: 16);
  if (value == null) return fallback;
  return Color(0xFF000000 | value);
}

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

        return Scaffold(
          floatingActionButton: FloatingActionButton.extended(
            onPressed: () => _openQuickCreate(context, ref, selected),
            icon: const Icon(Icons.add),
            label: const Text('New event'),
          ),
          body: RefreshIndicator(
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
                    _LegendDot(color: Color(0xFFFF7A29)),
                    SizedBox(width: 6),
                    Text('SOMS Events', style: TextStyle(fontSize: 12)),
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
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () {
                      final id = item['id'] as String?;
                      final eventId = id != null && id.startsWith('event-')
                          ? int.tryParse(id.substring('event-'.length))
                          : null;
                      if (eventId == null) return;
                      Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => OfficerEventDetailScreen(
                          eventId: eventId,
                          title: item['title'] as String? ?? '',
                        ),
                      ));
                    },
                  ),
            ],
          ),
          ),
        );
      },
    );
  }

  Future<void> _openQuickCreate(BuildContext context, WidgetRef ref, DateTime day) async {
    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => _QuickCreateEventSheet(initialDate: day),
    );

    if (result == true) {
      ref.invalidate(officerCalendarProvider);
      ref.invalidate(officerEventsProvider);
    }
  }

  /// Buckets each feed item into every calendar day it spans (SOMS
  /// events use "start"/"end" and can be multi-day).
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

/// Tap-a-day quick-create sheet — posts to POST /officer/events (added
/// alongside this feature; event creation was previously web-only, see
/// the historical comment in events_screen.dart). Collects a preset
/// type, a day count (converted to date_start/date_end before sending,
/// matching what the web quick-create modal sends), venue, description,
/// and the has_parade flag — the same shape Officer\EventController and
/// Api\Officer\EventController both validate via EventTypes::TYPES.
class _QuickCreateEventSheet extends ConsumerStatefulWidget {
  const _QuickCreateEventSheet({required this.initialDate});

  final DateTime initialDate;

  @override
  ConsumerState<_QuickCreateEventSheet> createState() => _QuickCreateEventSheetState();
}

class _QuickCreateEventSheetState extends ConsumerState<_QuickCreateEventSheet> {
  late DateTime _startDate = widget.initialDate;
  String _type = 'other';
  String? _activity;
  int _days = 1;
  bool _hasParade = false;
  bool _submitting = false;
  XFile? _pickedImage;
  Color _selectedColor = kEventColorPresets.first;

  final _titleController = TextEditingController();
  final _venueController = TextEditingController();
  final _descriptionController = TextEditingController();

  @override
  void dispose() {
    _titleController.dispose();
    _venueController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  DateTime get _endDate => _startDate.add(Duration(days: _days - 1));

  String _fmt(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  Future<void> _pickStartDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _startDate,
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now().add(const Duration(days: 365 * 2)),
    );
    if (picked != null) setState(() => _startDate = picked);
  }

  Future<void> _pickImage() async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 85, // matches backend's 2MB cap headroom
    );
    if (picked != null) setState(() => _pickedImage = picked);
  }

  Future<void> _submit() async {
    if (_titleController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Title is required.')),
      );
      return;
    }

    setState(() => _submitting = true);
    final api = ref.read(apiClientProvider);
    try {
      final descriptionText = _descriptionController.text.trim();
      final venueText = _venueController.text.trim();
      final formData = FormData.fromMap({
        'title': _titleController.text.trim(),
        'type': _type,
        'date_start': _fmt(_startDate),
        'date_end': _fmt(_endDate),
        'has_parade': _hasParade,
        'color': colorToHex(_selectedColor),
        if (descriptionText.isNotEmpty) 'description': descriptionText,
        if (venueText.isNotEmpty) 'venue': venueText,
        if (_pickedImage != null) 'image': await MultipartFile.fromFile(_pickedImage!.path),
      });
      await api.postMultipart('/officer/events', formData);
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _submitting = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('New event', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 4),
            InkWell(
              onTap: _pickImage,
              borderRadius: BorderRadius.circular(12),
              child: Container(
                height: 120,
                margin: const EdgeInsets.symmetric(vertical: 10),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
                  color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.4),
                  image: _pickedImage != null
                      ? DecorationImage(image: FileImage(File(_pickedImage!.path)), fit: BoxFit.cover)
                      : null,
                ),
                child: _pickedImage == null
                    ? Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.add_photo_alternate_outlined, color: Theme.of(context).colorScheme.onSurfaceVariant),
                          const SizedBox(height: 4),
                          Text('Add a cover photo (optional)',
                              style: TextStyle(fontSize: 12, color: Theme.of(context).colorScheme.onSurfaceVariant)),
                        ],
                      )
                    : Align(
                        alignment: Alignment.topRight,
                        child: IconButton(
                          icon: const CircleAvatar(radius: 12, backgroundColor: Colors.black54, child: Icon(Icons.close, size: 14, color: Colors.white)),
                          onPressed: () => setState(() => _pickedImage = null),
                        ),
                      ),
              ),
            ),
            InkWell(
              onTap: _pickStartDate,
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Row(
                  children: [
                    const Icon(Icons.calendar_today, size: 16),
                    const SizedBox(width: 8),
                    Text(_fmt(_startDate)),
                    const Spacer(),
                    const Text('Change', style: TextStyle(fontSize: 12)),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            Text('Calendar color', style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 6),
            Row(
              children: kEventColorPresets.map((c) {
                final selected = c.toARGB32() == _selectedColor.toARGB32();
                return Padding(
                  padding: const EdgeInsets.only(right: 10),
                  child: GestureDetector(
                    onTap: () => setState(() => _selectedColor = c),
                    child: Container(
                      width: 28,
                      height: 28,
                      decoration: BoxDecoration(
                        color: c,
                        shape: BoxShape.circle,
                        border: selected ? Border.all(color: Colors.white, width: 2.5) : null,
                        boxShadow: selected ? [BoxShadow(color: c.withValues(alpha: 0.6), blurRadius: 6)] : null,
                      ),
                    ),
                  ),
                );
              }).toList(),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              value: _type,
              decoration: const InputDecoration(labelText: 'Event type'),
              isExpanded: true,
              items: kEventTypePresets.entries
                  .map((e) => DropdownMenuItem(
                        value: e.key,
                        child: Text('${e.value.emoji} ${e.value.label}', overflow: TextOverflow.ellipsis),
                      ))
                  .toList(),
              onChanged: (value) {
                if (value == null) return;
                setState(() {
                  _type = value;
                  if (value != 'foundation_day') _activity = null;
                  if (_titleController.text.trim().isEmpty && value != 'other' && value != 'foundation_day') {
                    _titleController.text = kEventTypePresets[value]!.label;
                  }
                });
              },
            ),
            if (_type == 'foundation_day') ...[
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                value: _activity,
                decoration: const InputDecoration(labelText: 'Foundation Day activity (optional)'),
                isExpanded: true,
                items: kFoundationDayActivities
                    .map((a) => DropdownMenuItem(value: a, child: Text(a)))
                    .toList(),
                onChanged: (value) {
                  setState(() {
                    _activity = value;
                    if (value != null) _titleController.text = 'Foundation Day — $value';
                  });
                },
              ),
            ],
            const SizedBox(height: 12),
            TextField(controller: _titleController, decoration: const InputDecoration(labelText: 'Title')),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    initialValue: '1',
                    decoration: const InputDecoration(labelText: 'Days'),
                    keyboardType: TextInputType.number,
                    onChanged: (v) => setState(() => _days = int.tryParse(v)?.clamp(1, 30) ?? 1),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  flex: 2,
                  child: TextField(controller: _venueController, decoration: const InputDecoration(labelText: 'Venue')),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                _days == 1 ? 'Single day: ${_fmt(_startDate)}' : 'Runs ${_fmt(_startDate)} through ${_fmt(_endDate)} ($_days days)',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _descriptionController,
              decoration: const InputDecoration(labelText: 'Description (optional)'),
              minLines: 2,
              maxLines: 4,
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Include a Parade session'),
              subtitle: const Text('Time-in only, on each day'),
              value: _hasParade,
              onChanged: (v) => setState(() => _hasParade = v),
            ),
            const SizedBox(height: 8),
            FilledButton(
              onPressed: _submitting ? null : _submit,
              child: _submitting
                  ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Create event'),
            ),
          ],
        ),
      ),
    );
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
