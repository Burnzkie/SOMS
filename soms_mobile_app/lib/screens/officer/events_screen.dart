import 'dart:io';

import 'package:dio/dio.dart' show FormData, MultipartFile;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../models/event.dart';
import '../../providers/auth_provider.dart';
import '../../providers/officer_providers.dart';
import '../../widgets/status_views.dart';
import 'calendar_screen.dart' show colorToHex, hexToColor, kEventColorPresets, kEventTypePresets;
import 'scan_screen.dart';

/// Event list here; the detail screen (below) now also supports edit,
/// delete, and reschedule (date-picker, since table_calendar has no drag
/// gesture) — all three call the same POST/PATCH/DELETE
/// /officer/events endpoints the web calendar's modal and drag handler
/// use, via EventSetupService on the backend so the two surfaces can't
/// drift apart. Creation itself still happens via the calendar's
/// tap-a-day quick-create sheet (OfficerCalendarScreen).
class OfficerEventsScreen extends ConsumerWidget {
  const OfficerEventsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final events = ref.watch(officerEventsProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(officerEventsProvider),
      child: events.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(officerEventsProvider)),
        data: (page) {
          if (page.data.isEmpty) {
            return const EmptyStateView(message: 'No events yet.', icon: Icons.event_outlined);
          }
          return ListView.builder(
            padding: const EdgeInsets.all(12),
            itemCount: page.data.length,
            itemBuilder: (context, i) {
              final event = page.data[i];
              return Card(
                child: ListTile(
                  leading: Icon(event.isPublished ? Icons.event_available_outlined : Icons.edit_note),
                  title: Text(event.title),
                  subtitle: Text(event.isPublished ? 'Published' : 'Draft'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => OfficerEventDetailScreen(eventId: event.id, title: event.title)),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class OfficerEventDetailScreen extends ConsumerWidget {
  const OfficerEventDetailScreen({super.key, required this.eventId, required this.title});

  final int eventId;
  final String title;

  Future<void> _refreshEverywhere(WidgetRef ref) async {
    ref.invalidate(officerEventDetailProvider(eventId));
    ref.invalidate(officerEventsProvider);
    ref.invalidate(officerCalendarProvider);
  }

  Future<void> _edit(BuildContext context, WidgetRef ref, SomsEvent event) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => _EditEventSheet(event: event),
    );
    if (saved == true) await _refreshEverywhere(ref);
  }

  Future<void> _reschedule(BuildContext context, WidgetRef ref, SomsEvent event) async {
    final currentStart = DateTime.tryParse(event.dateStart);
    final currentEnd = DateTime.tryParse(event.dateEnd);
    if (currentStart == null || currentEnd == null) return;
    final durationDays = currentEnd.difference(currentStart).inDays + 1;

    final picked = await showDatePicker(
      context: context,
      initialDate: currentStart,
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now().add(const Duration(days: 365 * 2)),
      helpText: 'New start date (keeps the same $durationDays-day length)',
    );
    if (picked == null) return;

    final api = ref.read(apiClientProvider);
    try {
      await api.patch('/officer/events/$eventId/reschedule', data: {
        'date_start': DateFormat('yyyy-MM-dd').format(picked),
      });
      await _refreshEverywhere(ref);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Event rescheduled.')));
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Future<void> _delete(BuildContext context, WidgetRef ref) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete event?'),
        content: Text('Delete "$title" and everything under it (days, sessions, fine rules)? This can\'t be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete')),
        ],
      ),
    );
    if (confirmed != true) return;

    final api = ref.read(apiClientProvider);
    try {
      await api.delete('/officer/events/$eventId');
      ref.invalidate(officerEventsProvider);
      ref.invalidate(officerCalendarProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Event deleted.')));
        Navigator.of(context).pop();
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(officerEventDetailProvider(eventId));

    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        actions: detail.maybeWhen(
          data: (event) => [
            IconButton(
              icon: const Icon(Icons.edit_outlined),
              tooltip: 'Edit details',
              onPressed: () => _edit(context, ref, event),
            ),
            IconButton(
              icon: const Icon(Icons.delete_outline),
              tooltip: 'Delete event',
              onPressed: () => _delete(context, ref),
            ),
          ],
          orElse: () => const [],
        ),
      ),
      body: detail.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) =>
            ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(officerEventDetailProvider(eventId))),
        data: (event) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (event.venue != null) Text(event.venue!, style: Theme.of(context).textTheme.bodyMedium),
            InkWell(
              onTap: () => _reschedule(context, ref, event),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Row(
                  children: [
                    const Icon(Icons.calendar_today, size: 16),
                    const SizedBox(width: 8),
                    Text('${event.dateStart} — ${event.dateEnd}'),
                    const Spacer(),
                    const Text('Reschedule', style: TextStyle(fontSize: 12)),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 8),
            for (final day in event.eventDays) ...[
              Text(_dayLabel(day), style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 6),
              for (final session in day.sessions)
                _SessionCard(session: session, eventTitle: title),
              const SizedBox(height: 16),
            ],
            if (event.fineRules.isNotEmpty) ...[
              const Divider(),
              const SizedBox(height: 8),
              Text('Fine amounts', style: Theme.of(context).textTheme.titleMedium),
              for (final rule in event.fineRules)
                ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  title: Text(rule.violationType.replaceAll('_', ' ')),
                  trailing: Text('₱${rule.amount.toStringAsFixed(2)}'),
                ),
            ],
          ],
        ),
      ),
    );
  }

  String _dayLabel(EventDay day) {
    if (day.label != null) return day.label!;
    try {
      return DateFormat.yMMMEd().format(DateTime.parse(day.date));
    } catch (_) {
      return day.date;
    }
  }
}

/// Edit sheet for an event's own details — title/type/venue/description.
/// Dates aren't editable here; that's what the detail screen's
/// "Reschedule" row is for (keeps day/session generation in sync via
/// EventSetupService::reschedule() on the backend).
class _EditEventSheet extends ConsumerStatefulWidget {
  const _EditEventSheet({required this.event});

  final SomsEvent event;

  @override
  ConsumerState<_EditEventSheet> createState() => _EditEventSheetState();
}

class _EditEventSheetState extends ConsumerState<_EditEventSheet> {
  late final _titleController = TextEditingController(text: widget.event.title);
  late final _venueController = TextEditingController(text: widget.event.venue ?? '');
  late final _descriptionController = TextEditingController(text: widget.event.description ?? '');
  late String _type = kEventTypePresets.containsKey(widget.event.type) ? widget.event.type : 'other';
  bool _submitting = false;
  XFile? _pickedImage;
  late Color _selectedColor = hexToColor(widget.event.color);

  Future<void> _pickImage() async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (picked != null) setState(() => _pickedImage = picked);
  }

  @override
  void dispose() {
    _titleController.dispose();
    _venueController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_titleController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Title is required.')));
      return;
    }

    setState(() => _submitting = true);
    final api = ref.read(apiClientProvider);
    try {
      final descriptionText = _descriptionController.text.trim();
      final venueText = _venueController.text.trim();
      if (_pickedImage != null) {
        final formData = FormData.fromMap({
          '_method': 'PATCH',
          'title': _titleController.text.trim(),
          'type': _type,
          'color': colorToHex(_selectedColor),
          if (descriptionText.isNotEmpty) 'description': descriptionText,
          if (venueText.isNotEmpty) 'venue': venueText,
          'image': await MultipartFile.fromFile(_pickedImage!.path),
        });
        await api.postMultipart('/officer/events/${widget.event.id}', formData);
      } else {
        await api.patch('/officer/events/${widget.event.id}', data: {
          'title': _titleController.text.trim(),
          'description': descriptionText.isEmpty ? null : descriptionText,
          'venue': venueText.isEmpty ? null : venueText,
          'type': _type,
          'color': colorToHex(_selectedColor),
        });
      }
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
            Text('Edit event', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            InkWell(
              onTap: _pickImage,
              borderRadius: BorderRadius.circular(12),
              child: Container(
                height: 120,
                margin: const EdgeInsets.only(bottom: 12),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
                  color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.4),
                  image: _pickedImage != null
                      ? DecorationImage(image: FileImage(File(_pickedImage!.path)), fit: BoxFit.cover)
                      : (widget.event.imageUrl != null
                          ? DecorationImage(image: NetworkImage(widget.event.imageUrl!), fit: BoxFit.cover)
                          : null),
                ),
                child: (_pickedImage == null && widget.event.imageUrl == null)
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
                        alignment: Alignment.bottomRight,
                        child: Padding(
                          padding: const EdgeInsets.all(6),
                          child: CircleAvatar(
                            radius: 12,
                            backgroundColor: Colors.black54,
                            child: Icon(Icons.edit, size: 13, color: Colors.white),
                          ),
                        ),
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
              onChanged: (value) => setState(() => _type = value ?? _type),
            ),
            const SizedBox(height: 12),
            TextField(controller: _titleController, decoration: const InputDecoration(labelText: 'Title')),
            const SizedBox(height: 12),
            TextField(controller: _venueController, decoration: const InputDecoration(labelText: 'Venue')),
            const SizedBox(height: 12),
            TextField(
              controller: _descriptionController,
              decoration: const InputDecoration(labelText: 'Description (optional)'),
              minLines: 2,
              maxLines: 4,
            ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: _submitting ? null : _submit,
              child: _submitting
                  ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Save changes'),
            ),
          ],
        ),
      ),
    );
  }
}

class _SessionCard extends ConsumerWidget {
  const _SessionCard({required this.session, required this.eventTitle});

  final EventSession session;
  final String eventTitle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Card(
      child: ListTile(
        leading: Icon(session.finesIssued ? Icons.lock_outline : Icons.lock_open_outlined),
        title: Text('${_titleCase(session.sessionType)} — ${session.timeinStart} to ${session.timeinEnd}'),
        subtitle: Text(session.finesIssued ? 'Fines issued' : 'Fines not yet issued'),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Delegate access can still matter even after fines are
            // issued (e.g. handing off a late walk-in override), so this
            // stays available regardless of finesIssued — unlike Scan.
            IconButton(
              icon: const Icon(Icons.group_outlined),
              tooltip: 'Delegates',
              onPressed: () => _openDelegates(context, ref, session),
            ),
            // Scanning after fines are issued for the session is still
            // allowed server-side (FinePolicy/AttendanceController don't
            // block it), but there's no point offering it here — a
            // closed session's window has already passed.
            if (!session.finesIssued)
              FilledButton.tonalIcon(
                icon: const Icon(Icons.qr_code_scanner, size: 18),
                label: const Text('Scan'),
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => OfficerScanScreen(session: session, eventTitle: eventTitle),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  String _titleCase(String s) => s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';

  /// Session-scoped delegate management — mirrors the "Delegates" card on
  /// the web event detail page (officer/events/show.blade.php). A 403
  /// here just means this officer lacks manage_attendance; shown as an
  /// inline message rather than hiding the entry point, since whether an
  /// officer has that permission isn't known client-side ahead of time.
  Future<void> _openDelegates(BuildContext context, WidgetRef ref, EventSession session) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => _DelegatesSheet(session: session, eventTitle: eventTitle),
    );
  }
}

class _DelegatesSheet extends ConsumerStatefulWidget {
  const _DelegatesSheet({required this.session, required this.eventTitle});

  final EventSession session;
  final String eventTitle;

  @override
  ConsumerState<_DelegatesSheet> createState() => _DelegatesSheetState();
}

class _DelegatesSheetState extends ConsumerState<_DelegatesSheet> {
  final _studentIdCtrl = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _studentIdCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final delegates = ref.watch(sessionDelegatesProvider(widget.session.id));

    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Delegates — ${widget.eventTitle}', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 4),
          Text(
            'Anyone listed here can scan/override attendance for this session only.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: 12),
          delegates.when(
            loading: () => const Padding(
              padding: EdgeInsets.symmetric(vertical: 16),
              child: Center(child: CircularProgressIndicator()),
            ),
            error: (e, _) => Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Text('$e', style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
            data: (list) => list.isEmpty
                ? const Padding(
                    padding: EdgeInsets.symmetric(vertical: 8),
                    child: Text('No delegates assigned — only Executive/Administrative officers can scan or override this session.'),
                  )
                : Column(
                    children: [
                      for (final d in list)
                        ListTile(
                          dense: true,
                          contentPadding: EdgeInsets.zero,
                          leading: const Icon(Icons.person_outline),
                          title: Text(d.userName ?? 'Student #${d.userId}'),
                          subtitle: Text(d.userStudentId ?? ''),
                          trailing: IconButton(
                            icon: const Icon(Icons.remove_circle_outline),
                            tooltip: 'Remove',
                            onPressed: () => _removeDelegate(d),
                          ),
                        ),
                    ],
                  ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _studentIdCtrl,
                  decoration: const InputDecoration(labelText: 'Student ID to assign'),
                ),
              ),
              const SizedBox(width: 10),
              FilledButton(
                onPressed: _submitting ? null : _assignDelegate,
                child: _submitting
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Text('Assign'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _assignDelegate() async {
    final studentId = _studentIdCtrl.text.trim();
    if (studentId.isEmpty) return;

    setState(() => _submitting = true);
    final api = ref.read(apiClientProvider);
    try {
      await api.post('/officer/attendance/sessions/${widget.session.id}/delegates', data: {
        'student_id': studentId,
      });
      _studentIdCtrl.clear();
      ref.invalidate(sessionDelegatesProvider(widget.session.id));
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _removeDelegate(SessionDelegate delegate) async {
    final api = ref.read(apiClientProvider);
    try {
      await api.delete('/officer/attendance/sessions/${widget.session.id}/delegates/${delegate.id}');
      ref.invalidate(sessionDelegatesProvider(widget.session.id));
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }
}
