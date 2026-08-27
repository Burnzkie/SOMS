import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../models/event.dart';
import '../../providers/officer_providers.dart';
import '../../widgets/status_views.dart';
import 'scan_screen.dart';

/// Read-only by design — event/session creation stays a web-only flow
/// (see the doc comment on Api\Officer\EventController). This mirrors
/// that boundary rather than half-building a mobile create form against
/// an endpoint that doesn't exist.
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

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(officerEventDetailProvider(eventId));

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: detail.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) =>
            ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(officerEventDetailProvider(eventId))),
        data: (event) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (event.venue != null) Text(event.venue!, style: Theme.of(context).textTheme.bodyMedium),
            const SizedBox(height: 16),
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

class _SessionCard extends StatelessWidget {
  const _SessionCard({required this.session, required this.eventTitle});

  final EventSession session;
  final String eventTitle;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: Icon(session.finesIssued ? Icons.lock_outline : Icons.lock_open_outlined),
        title: Text('${_titleCase(session.sessionType)} — ${session.timeinStart} to ${session.timeinEnd}'),
        subtitle: Text(session.finesIssued ? 'Fines issued' : 'Fines not yet issued'),
        // Scanning after fines are issued for the session is still allowed
        // server-side (FinePolicy/AttendanceController don't block it), but
        // there's no point offering it here — a closed session's window
        // has already passed.
        trailing: session.finesIssued
            ? null
            : FilledButton.tonalIcon(
                icon: const Icon(Icons.qr_code_scanner, size: 18),
                label: const Text('Scan'),
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => OfficerScanScreen(session: session, eventTitle: eventTitle),
                  ),
                ),
              ),
      ),
    );
  }

  String _titleCase(String s) => s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';
}
