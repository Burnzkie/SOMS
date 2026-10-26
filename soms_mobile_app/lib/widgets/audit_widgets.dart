// lib/widgets/audit_widgets.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/app_theme.dart';
import '../models/audit_models.dart';

// Shared by the Auditor's workspace and the Admin's findings screen, so the
// two look and read the same.

final _moneyFormat = NumberFormat('#,##0.00');
final _dayFormat = DateFormat('MMM d, y');
final _dateTimeFormat = DateFormat('MMM d, h:mm a');

String formatPeso(double value) => '₱${_moneyFormat.format(value)}';
String formatDay(DateTime? value) => value == null ? '—' : _dayFormat.format(value);
String formatDateTime(DateTime? value) => value == null ? '—' : _dateTimeFormat.format(value);
String humanize(String value) => value.replaceAll('_', ' ');
String capitalize(String value) => value.isEmpty ? value : value[0].toUpperCase() + value.substring(1);
String clipText(String value, int max) => value.length <= max ? value : '${value.substring(0, max - 1)}…';

Color severityColor(String severity) => switch (severity) {
      'high' => AppColors.roseText,
      'medium' => AppColors.amberText,
      _ => AppColors.primary,
    };

Color fineStatusColor(String status) => switch (status) {
      'paid' => AppColors.emeraldText,
      'waived' => AppColors.primary,
      _ => AppColors.roseText,
    };

Color findingStatusColor(String status) => switch (status) {
      'resolved' => AppColors.emeraldText,
      'acknowledged' => AppColors.primary,
      'dismissed' => AppColors.textMuted,
      _ => AppColors.amberText,
    };

/// Small rounded label, e.g. "High" or "Resolved".
class AuditPill extends StatelessWidget {
  const AuditPill(this.label, this.color, {super.key});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: color),
      ),
    );
  }
}

/// "Page 2 of 5" with previous / next, for the paged lists.
class AuditPager extends StatelessWidget {
  const AuditPager({super.key, required this.page, required this.lastPage, required this.onPage});

  final int page;
  final int lastPage;
  final ValueChanged<int> onPage;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        TextButton.icon(
          onPressed: page > 1 ? () => onPage(page - 1) : null,
          icon: const Icon(Icons.chevron_left),
          label: const Text('Previous'),
        ),
        Text('Page $page of $lastPage', style: const TextStyle(fontSize: 12.5, color: AppColors.textMuted)),
        // A plain Row (not TextButton.icon's iconAlignment, which needs Flutter 3.28+).
        TextButton(
          onPressed: page < lastPage ? () => onPage(page + 1) : null,
          child: const Row(
            mainAxisSize: MainAxisSize.min,
            children: [Text('Next'), Icon(Icons.chevron_right)],
          ),
        ),
      ],
    );
  }
}

/// Detail of one audit finding, for a bottom sheet. The Auditor's sheet is
/// read-only; the Admin's passes [footer] with the acknowledge / resolve actions.
class AuditFindingDetail extends StatelessWidget {
  const AuditFindingDetail({super.key, required this.finding, this.footer});

  final AuditFinding finding;
  final Widget? footer;

  Widget _section(BuildContext context, String title, String body) {
    return Padding(
      padding: const EdgeInsets.only(top: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: Theme.of(context).textTheme.labelLarge?.copyWith(color: AppColors.textMuted)),
          const SizedBox(height: 4),
          Text(body),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final reporter = finding.reporterName == null ? '' : ' by ${finding.reporterName}';
    final resolver = finding.resolverName == null ? '' : ' by ${finding.resolverName}';
    final referenceId = finding.referenceId == null ? '' : ' #${finding.referenceId}';

    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: 8,
              children: [
                AuditPill(capitalize(finding.severity), severityColor(finding.severity)),
                AuditPill(capitalize(finding.status), findingStatusColor(finding.status)),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              finding.title,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            Text(
              'Finding #${finding.id} · reported ${formatDay(finding.reportedAt)}$reporter',
              style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
            ),
            _section(context, 'Record', '${capitalize(finding.referenceType)}$referenceId'),
            _section(context, 'Issue discovered', finding.issue),
            if ((finding.evidence ?? '').isNotEmpty) _section(context, 'Evidence / notes', finding.evidence!),
            if ((finding.resolutionNote ?? '').isNotEmpty)
              _section(
                context,
                'Outcome — ${capitalize(finding.status)}$resolver on ${formatDay(finding.resolvedAt)}',
                finding.resolutionNote!,
              ),
            if (footer != null) ...[const SizedBox(height: 18), footer!],
          ],
        ),
      ),
    );
  }
}
