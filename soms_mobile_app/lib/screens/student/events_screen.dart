import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../models/event.dart';
import '../../providers/student_providers.dart';
import '../../widgets/status_views.dart';

class StudentEventsScreen extends ConsumerWidget {
  const StudentEventsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final events = ref.watch(studentEventsProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(studentEventsProvider),
      child: events.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(studentEventsProvider)),
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
                  leading: const Icon(Icons.event_outlined),
                  title: Text(event.title),
                  subtitle: Text(_range(event.dateStart, event.dateEnd)),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => StudentEventDetailScreen(eventId: event.id, title: event.title)),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }

  String _range(String start, String end) {
    try {
      final s = DateFormat.yMMMd().format(DateTime.parse(start));
      final e = DateFormat.yMMMd().format(DateTime.parse(end));
      return s == e ? s : '$s – $e';
    } catch (_) {
      return '';
    }
  }
}

class StudentEventDetailScreen extends ConsumerWidget {
  const StudentEventDetailScreen({super.key, required this.eventId, required this.title});

  final int eventId;
  final String title;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(studentEventDetailProvider(eventId));

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: detail.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorRetryView(
          message: '$e',
          onRetry: () => ref.invalidate(studentEventDetailProvider(eventId)),
        ),
        data: (result) {
          final (event, attendance) = result;
          final attendanceBySession = {for (final a in attendance) a.eventSessionId: a};

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (event.venue != null) _InfoRow(icon: Icons.place_outlined, text: event.venue!),
              if (event.description != null) ...[
                const SizedBox(height: 12),
                Text(event.description!),
              ],
              const SizedBox(height: 20),
              Text('Your attendance', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              for (final day in event.eventDays) ...[
                Text(day.label ?? day.date, style: Theme.of(context).textTheme.labelLarge),
                for (final session in day.sessions)
                  _SessionAttendanceTile(session: session, record: attendanceBySession[session.id]),
                const SizedBox(height: 12),
              ],
              if (event.fineRules.isNotEmpty) ...[
                const Divider(height: 32),
                Text('Fine amounts', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                for (final rule in event.fineRules)
                  ListTile(
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                    title: Text(rule.violationType.replaceAll('_', ' ')),
                    trailing: Text('₱${rule.amount.toStringAsFixed(2)}'),
                  ),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _SessionAttendanceTile extends StatelessWidget {
  const _SessionAttendanceTile({required this.session, this.record});

  final EventSession session;
  final MyAttendanceRecord? record;

  @override
  Widget build(BuildContext context) {
    final status = record?.status;
    final (color, icon) = switch (status) {
      'present' => (Colors.green, Icons.check_circle_outline),
      'late' => (Colors.orange, Icons.schedule),
      'flagged_for_review' => (Colors.orange, Icons.flag_outlined),
      'absent' => (Colors.red, Icons.cancel_outlined),
      _ => (Theme.of(context).colorScheme.outline, Icons.remove_circle_outline),
    };

    return ListTile(
      dense: true,
      contentPadding: EdgeInsets.zero,
      leading: Icon(icon, color: color),
      title: Text('${_titleCase(session.sessionType)} — ${session.timeinStart} to ${session.timeinEnd}'),
      trailing: Text(status ?? 'no record', style: TextStyle(color: color)),
    );
  }

  String _titleCase(String s) => s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 18, color: Theme.of(context).colorScheme.outline),
        const SizedBox(width: 6),
        Expanded(child: Text(text)),
      ],
    );
  }
}
