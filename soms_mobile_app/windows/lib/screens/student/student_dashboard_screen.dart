import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../providers/student_providers.dart';
import '../../widgets/status_views.dart';

class StudentDashboardScreen extends ConsumerWidget {
  const StudentDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashboard = ref.watch(studentDashboardProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(studentDashboardProvider),
      child: dashboard.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(studentDashboardProvider)),
        data: (data) {
          final unpaidCount = data['unpaid_fines_count'] as int? ?? 0;
          final unpaidAmount = double.tryParse('${data['unpaid_fines_amount'] ?? 0}') ?? 0;
          final events = (data['upcoming_events'] as List<dynamic>? ?? []);
          final announcements = (data['recent_announcements'] as List<dynamic>? ?? []);

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Card(
                color: unpaidCount > 0
                    ? Theme.of(context).colorScheme.errorContainer
                    : Theme.of(context).colorScheme.primaryContainer,
                child: ListTile(
                  leading: Icon(unpaidCount > 0 ? Icons.receipt_long : Icons.check_circle_outline),
                  title: Text(unpaidCount > 0
                      ? '$unpaidCount unpaid fine${unpaidCount == 1 ? '' : 's'} — ₱${unpaidAmount.toStringAsFixed(2)}'
                      : 'No unpaid fines'),
                  subtitle: unpaidCount > 0
                      ? const Text('Pay in person at the Treasurer\'s office.')
                      : null,
                ),
              ),
              const SizedBox(height: 16),
              Text('Upcoming events', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              if (events.isEmpty) const Text('Nothing scheduled right now.'),
              for (final e in events)
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.event_outlined),
                    title: Text(e['title'] as String? ?? ''),
                    subtitle: Text(_formatRange(e['date_start'], e['date_end'])),
                  ),
                ),
              const SizedBox(height: 16),
              Text('Recent announcements', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              if (announcements.isEmpty) const Text('No announcements yet.'),
              for (final a in announcements)
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.campaign_outlined),
                    title: Text(a['title'] as String? ?? ''),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }

  String _formatRange(dynamic start, dynamic end) {
    try {
      final s = DateFormat.MMMd().format(DateTime.parse(start as String));
      final e = DateFormat.MMMd().format(DateTime.parse(end as String));
      return s == e ? s : '$s – $e';
    } catch (_) {
      return '';
    }
  }
}
