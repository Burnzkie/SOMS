import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../providers/admin_providers.dart';
import '../../widgets/status_views.dart';

class AdminLogsScreen extends ConsumerWidget {
  const AdminLogsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final health = ref.watch(systemHealthProvider);
    final logs = ref.watch(activityLogsProvider);

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(systemHealthProvider);
        ref.invalidate(activityLogsProvider);
      },
      child: ListView(
        padding: const EdgeInsets.all(12),
        children: [
          health.when(
            loading: () => const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: CircularProgressIndicator()),
            ),
            error: (e, _) => ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(systemHealthProvider)),
            data: (h) => Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('System health', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                _StatusRow(
                  ok: h.logChainOk,
                  okLabel: 'Activity log chain intact',
                  badLabel: 'Log chain integrity check FAILED — review immediately',
                ),
                _StatusRow(
                  ok: !h.queueStale,
                  okLabel: 'Fine-issuance queue worker healthy',
                  badLabel: 'Queue worker heartbeat stale (>2h) — check Render worker process',
                ),
                _StatusRow(
                  ok: h.treasurerActive,
                  okLabel: 'Treasurer position filled',
                  badLabel: 'No active Treasurer — Admin is fallback for fine clear/waive',
                ),
              ],
            ),
          ),
          const Divider(height: 32),
          Text('Activity log', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          logs.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(activityLogsProvider)),
            data: (data) {
              final (page, chainOk) = data;
              if (!chainOk) {
                return const Padding(
                  padding: EdgeInsets.symmetric(vertical: 8),
                  child: Text(
                    '⚠ Chain integrity check failed on this page of results.',
                    style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold),
                  ),
                );
              }
              if (page.data.isEmpty) {
                return const EmptyStateView(message: 'No activity yet.', icon: Icons.receipt_long_outlined);
              }
              return Column(
                children: [
                  for (final entry in page.data)
                    ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.circle, size: 8),
                      title: Text(entry.action.replaceAll('_', ' ')),
                      subtitle: entry.userName != null ? Text(entry.userName!) : null,
                      trailing: Text(entry.createdAt, style: const TextStyle(fontSize: 11)),
                    ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}

class _StatusRow extends StatelessWidget {
  const _StatusRow({required this.ok, required this.okLabel, required this.badLabel});

  final bool ok;
  final String okLabel;
  final String badLabel;

  @override
  Widget build(BuildContext context) {
    final color = ok ? Colors.green : Colors.red;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(ok ? Icons.check_circle : Icons.error, color: color, size: 18),
          const SizedBox(width: 8),
          Expanded(child: Text(ok ? okLabel : badLabel, style: TextStyle(color: color, fontSize: 13))),
        ],
      ),
    );
  }
}
