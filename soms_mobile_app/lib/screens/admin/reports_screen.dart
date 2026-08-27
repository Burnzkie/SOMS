import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../models/fine.dart';
import '../../providers/admin_providers.dart';
import '../../widgets/status_views.dart';

/// PDF generation itself stays web-only (Admin\ReportController); this
/// shows the same underlying figures for at-a-glance mobile reference.
/// Paid and waived are kept visually distinct throughout -- waived fines
/// are not revenue (05-Attendance-Fines.md, Fine Waiver section).
class AdminReportsScreen extends ConsumerWidget {
  const AdminReportsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final report = ref.watch(fineReportProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(fineReportProvider),
      child: report.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(fineReportProvider)),
        data: (data) {
          final (paid, waived, summary) = data;
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Row(
                children: [
                  Expanded(
                    child: _SummaryCard(
                      label: 'Collected',
                      amount: summary.totalCollected,
                      color: Colors.green,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _SummaryCard(
                      label: 'Waived (not revenue)',
                      amount: summary.totalWaived,
                      color: Colors.blueGrey,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),
              Text('Paid (${paid.length})', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              if (paid.isEmpty) const Text('None yet.'),
              for (final f in paid) _ReportRow(fine: f, color: Colors.green),
              const SizedBox(height: 24),
              Text('Waived (${waived.length})', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              if (waived.isEmpty) const Text('None yet.'),
              for (final f in waived) _ReportRow(fine: f, color: Colors.blueGrey),
            ],
          );
        },
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.label, required this.amount, required this.color});

  final String label;
  final double amount;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Card(
      color: color.withValues(alpha: 0.1),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: TextStyle(color: color, fontWeight: FontWeight.bold, fontSize: 12)),
            const SizedBox(height: 6),
            Text('₱${amount.toStringAsFixed(2)}', style: Theme.of(context).textTheme.titleLarge),
          ],
        ),
      ),
    );
  }
}

class _ReportRow extends StatelessWidget {
  const _ReportRow({required this.fine, required this.color});

  final Fine fine;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      dense: true,
      contentPadding: EdgeInsets.zero,
      leading: Icon(Icons.circle, size: 10, color: color),
      title: Text(fine.studentName ?? 'Student #${fine.userId}'),
      subtitle: Text(fine.violationType.replaceAll('_', ' ')),
      trailing: Text('₱${fine.amount.toStringAsFixed(2)}'),
    );
  }
}
