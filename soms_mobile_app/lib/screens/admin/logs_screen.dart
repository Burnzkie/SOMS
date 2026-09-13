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
              // Fix (Sep 2026) — this used to `return` here, replacing the
              // ENTIRE log list with just this warning text whenever the
              // chain check failed. That's exactly backwards: a failed
              // integrity check is when reviewing the actual entries
              // matters most, and this was hiding them at precisely that
              // moment. Now it's a banner ABOVE the list, and the list
              // (page.data) always renders below it regardless of chainOk.
              if (page.data.isEmpty) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (!chainOk) const _ChainWarningBanner(),
                    const EmptyStateView(message: 'No activity yet.', icon: Icons.receipt_long_outlined),
                  ],
                );
              }
              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (!chainOk) const _ChainWarningBanner(),
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

class _ChainWarningBanner extends StatelessWidget {
  const _ChainWarningBanner();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.only(bottom: 8),
      child: Text(
        '⚠ Chain integrity check failed on this page of results — entries below could not be verified.',
        style: TextStyle(color: Color(0xFFE51B0D), fontWeight: FontWeight.bold, fontSize: 12.5),
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
    // Accessibility fix (Sep 2026) — Colors.green/Colors.red rendered as
    // actual status text here (not just an icon), measuring 2.78:1 / 3.68:1
    // on white — under WCAG AA's 4.5:1. Darker same-hue variants, consistent
    // with the fix applied to the fines screens.
    final color = ok ? const Color(0xFF39843C) : const Color(0xFFE51B0D);
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
