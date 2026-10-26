// lib/screens/officer/flagged_scans_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../models/flagged_scan.dart';
import '../../providers/auth_provider.dart';
import '../../providers/officer_providers.dart';
import '../../widgets/status_views.dart';

/// Review queue for offline scans flagged for device clock drift — mobile
/// counterpart of the "Flagged scans" panel on the web scan station.
/// Approve -> the student counts as present. Reject -> marked absent and,
/// once the window has closed, fined. Students with a pending flagged scan
/// are not fined until an officer decides.
class FlaggedScansScreen extends ConsumerWidget {
  const FlaggedScansScreen({super.key, required this.sessionId, required this.eventTitle});

  final int sessionId;
  final String eventTitle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final scans = ref.watch(flaggedScansProvider(sessionId));

    return Scaffold(
      appBar: AppBar(title: Text('Flagged scans · $eventTitle')),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(flaggedScansProvider(sessionId)),
        child: scans.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) {
            if (e is ApiException && e.statusCode == 403) {
              return const AccessDeniedView(
                message: 'Only Executive/Administrative officers can review flagged scans.',
              );
            }
            return ErrorRetryView(
              message: '$e',
              onRetry: () => ref.invalidate(flaggedScansProvider(sessionId)),
            );
          },
          data: (list) {
            if (list.isEmpty) {
              return const EmptyStateView(
                message: 'No scans waiting for review.',
                icon: Icons.flag_outlined,
              );
            }
            return ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: list.length + 1,
              itemBuilder: (context, i) {
                if (i == 0) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Text(
                      '${list.length} pending. These were scanned offline on a device whose clock '
                      'was off by more than 10 minutes. Approve to mark the student present, or '
                      'reject to mark them absent (they will be fined once the window has closed).',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  );
                }
                return _FlaggedScanCard(
                  scan: list[i - 1],
                  onReviewed: () => ref.invalidate(flaggedScansProvider(sessionId)),
                );
              },
            );
          },
        ),
      ),
    );
  }
}

class _FlaggedScanCard extends ConsumerStatefulWidget {
  const _FlaggedScanCard({required this.scan, required this.onReviewed});

  final FlaggedScan scan;
  final VoidCallback onReviewed;

  @override
  ConsumerState<_FlaggedScanCard> createState() => _FlaggedScanCardState();
}

class _FlaggedScanCardState extends ConsumerState<_FlaggedScanCard> {
  // Disables both buttons while a request is in flight so a double-tap can't fire twice.
  bool _busy = false;

  /// "h:mm AM/PM" in the officer's local time, or a dash when missing.
  String _formatTime(DateTime? t) => t == null ? '—' : DateFormat('h:mm a').format(t);

  Future<bool> _confirmReject() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Reject this scan?'),
        content: Text('${widget.scan.studentName} will be marked absent and fined '
            'if the scan window has closed.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Reject')),
        ],
      ),
    );
    return confirmed == true;
  }

  Future<void> _review({required bool approve}) async {
    if (!approve && !await _confirmReject()) return;
    if (!mounted) return;

    setState(() => _busy = true);
    final api = ref.read(apiClientProvider);
    try {
      final res = await api.post(
        '/officer/attendance/${widget.scan.attendanceId}/review',
        data: {'decision': approve ? 'approve' : 'reject'},
      );
      widget.onReviewed();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(res['message'] as String? ?? 'Done.')),
        );
      }
    } on ApiException catch (e) {
      // 422 = someone else already decided this scan; refresh so it drops off the list.
      if (e.statusCode == 422) widget.onReviewed();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final scan = widget.scan;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.flag_outlined, color: Color(0xFFA86400)),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(scan.studentName, style: Theme.of(context).textTheme.titleMedium),
                      Text(
                        '${scan.studentId} · ${scan.isTimeOut ? 'Time out' : 'Time in'}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              'Device clock ${_formatTime(scan.deviceScannedAt)} · '
              'received ${_formatTime(scan.serverReceivedAt)}',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _busy ? null : () => _review(approve: false),
                    child: const Text('Reject'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: FilledButton(
                    onPressed: _busy ? null : () => _review(approve: true),
                    child: _busy
                        ? const SizedBox(
                            height: 18,
                            width: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Approve'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
