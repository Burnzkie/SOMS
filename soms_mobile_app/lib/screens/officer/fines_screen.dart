// fines_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../models/fine.dart';
import '../../models/paginated.dart';
import '../../models/student_fine_summary.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/status_views.dart';

/// Students with their overall fines, from GET /officer/fines/students.
/// [status] keeps students who have at least one fine in that status (null = all).
/// The endpoint paginates 20 students per page, so every page is loaded here
/// (capped at 25 pages so a bad response can never loop forever).
final officerStudentFinesProvider = FutureProvider.autoDispose
    .family<({List<StudentFineSummary> students, double grandUnpaid}), String?>(
        (ref, status) async {
  final api = ref.watch(apiClientProvider);
  final students = <StudentFineSummary>[];
  var grandUnpaid = 0.0;
  var page = 1;

  while (true) {
    final res = await api.get('/officer/fines/students', query: {
      'page': page,
      if (status != null) 'status': status,
    });
    final result = Paginated<StudentFineSummary>.fromJson(
      res['data'] as Map<String, dynamic>,
      StudentFineSummary.fromJson,
    );
    students.addAll(result.data);

    final meta = res['meta'] as Map<String, dynamic>?;
    grandUnpaid =
        double.tryParse('${meta?['grand_unpaid_total']}') ?? grandUnpaid;

    if (!result.hasMore || page >= 25) break;
    page++;
  }

  return (students: students, grandUnpaid: grandUnpaid);
});

class OfficerFinesScreen extends ConsumerStatefulWidget {
  const OfficerFinesScreen({super.key});

  @override
  ConsumerState<OfficerFinesScreen> createState() => _OfficerFinesScreenState();
}

class _OfficerFinesScreenState extends ConsumerState<OfficerFinesScreen> {
  String? _status = 'unpaid';

  @override
  Widget build(BuildContext context) {
    final fines = ref.watch(officerStudentFinesProvider(_status));

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
          child: Wrap(
            spacing: 8,
            children: [
              _FilterChip(
                  label: 'Unpaid',
                  selected: _status == 'unpaid',
                  onTap: () => setState(() => _status = 'unpaid')),
              _FilterChip(
                  label: 'Paid',
                  selected: _status == 'paid',
                  onTap: () => setState(() => _status = 'paid')),
              _FilterChip(
                  label: 'Waived',
                  selected: _status == 'waived',
                  onTap: () => setState(() => _status = 'waived')),
              _FilterChip(
                  label: 'All',
                  selected: _status == null,
                  onTap: () => setState(() => _status = null)),
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () async =>
                ref.invalidate(officerStudentFinesProvider(_status)),
            child: fines.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) {
                if (e is ApiException && e.statusCode == 403) {
                  return const AccessDeniedView(
                    message:
                        'Fines are Treasurer + Admin only. If your org has no active '
                        'Treasurer, ask an Admin to clear/waive on your behalf.',
                  );
                }
                return ErrorRetryView(
                    message: '$e',
                    onRetry: () =>
                        ref.invalidate(officerStudentFinesProvider(_status)));
              },
              data: (data) {
                if (data.students.isEmpty) {
                  return const EmptyStateView(
                      message: 'Nothing here.',
                      icon: Icons.receipt_long_outlined);
                }
                // Item 0 is the overall summary line; students follow.
                return ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: data.students.length + 1,
                  itemBuilder: (context, i) {
                    if (i == 0) {
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Text(
                          '${data.students.length} students · ₱${data.grandUnpaid.toStringAsFixed(2)} unpaid overall',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      );
                    }
                    return _StudentFineCard(
                      summary: data.students[i - 1],
                      onChanged: () =>
                          ref.invalidate(officerStudentFinesProvider(_status)),
                    );
                  },
                );
              },
            ),
          ),
        ),
      ],
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip(
      {required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
        label: Text(label), selected: selected, onSelected: (_) => onTap());
  }
}

/// One student: overall unpaid total, "Mark all paid" / "Waive all" buttons,
/// and an expandable list of their individual fines (per-fine actions stay
/// available for partial payments).
class _StudentFineCard extends ConsumerStatefulWidget {
  const _StudentFineCard({required this.summary, required this.onChanged});

  final StudentFineSummary summary;
  final VoidCallback onChanged;

  @override
  ConsumerState<_StudentFineCard> createState() => _StudentFineCardState();
}

class _StudentFineCardState extends ConsumerState<_StudentFineCard> {
  // Disables the buttons while a bulk request is in flight so a double-tap can't fire twice.
  bool _busy = false;

  /// [action] is 'clear' (mark all paid) or 'waive' (waive all) — same suffix the API route uses.
  Future<void> _settleAll(String action) async {
    final summary = widget.summary;
    final isClear = action == 'clear';
    final reasonController =
        TextEditingController(text: isClear ? 'Paid in person' : 'Waived');

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(isClear ? 'Mark all as paid?' : 'Waive all fines?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
                '${summary.name} · ${summary.unpaidCount} unpaid fine(s) · ₱${summary.unpaidTotal.toStringAsFixed(2)}'),
            const SizedBox(height: 12),
            TextField(
              controller: reasonController,
              decoration: const InputDecoration(
                  labelText: 'Reason (min. 5 characters)'),
              minLines: 1,
              maxLines: 3,
            ),
          ],
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel')),
          FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Confirm')),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;
    final reason = reasonController.text.trim();
    if (reason.length < 5) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Reason must be at least 5 characters.')));
      return;
    }

    setState(() => _busy = true);
    final api = ref.read(apiClientProvider);
    try {
      final res = await api.post(
          '/officer/fines/student/${summary.userId}/$action',
          data: {'reason': reason});
      final result = BulkSettleResult.fromJson(res);
      widget.onChanged();
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(result.message)));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final summary = widget.summary;
    final theme = Theme.of(context);

    return Card(
      child: Column(
        children: [
          ListTile(
            title: Text(summary.name),
            subtitle: Text(
              '${summary.studentId} · ${summary.unpaidCount} unpaid · ${summary.paidCount} paid · ${summary.waivedCount} waived',
            ),
            trailing: Text(
              '₱${summary.unpaidTotal.toStringAsFixed(2)}',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
                // Same accessible orange used for "unpaid" elsewhere in this app.
                color: summary.hasUnpaid
                    ? const Color(0xFFA86400)
                    : theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ),
          if (summary.hasUnpaid)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: _busy ? null : () => _settleAll('waive'),
                      child: const Text('Waive all'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: FilledButton(
                      onPressed: _busy ? null : () => _settleAll('clear'),
                      child: _busy
                          ? const SizedBox(
                              height: 18,
                              width: 18,
                              child: CircularProgressIndicator(strokeWidth: 2))
                          : const Text('Mark all paid'),
                    ),
                  ),
                ],
              ),
            ),
          Theme(
            data: theme.copyWith(dividerColor: Colors.transparent),
            child: ExpansionTile(
              title: Text('Fine details (${summary.fines.length})',
                  style: theme.textTheme.bodySmall),
              children: summary.fines
                  .map((fine) => _OfficerFineTile(
                      fine: fine,
                      onChanged: widget.onChanged,
                      showStudent: false))
                  .toList(),
            ),
          ),
        ],
      ),
    );
  }
}

class _OfficerFineTile extends ConsumerWidget {
  const _OfficerFineTile(
      {required this.fine, required this.onChanged, this.showStudent = true});

  final Fine fine;
  final VoidCallback onChanged;

  /// false inside a student's breakdown, where the student is already the card's title.
  final bool showStudent;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final color = switch (fine.status) {
      'paid' => Colors.green,
      'waived' => Colors.blueGrey,
      _ => Colors.orange,
    };
    // Accessibility fix (Sep 2026) — Colors.green/.orange/.blueGrey as TEXT
    // on white measured 2.16–4.37:1, under WCAG AA's 4.5:1 for normal text
    // (same category of bug found and fixed in the web app's status badges).
    // Darker same-hue variants for the actual status label; `color` above
    // still drives the icon + its soft background tint, which don't need
    // the same contrast ratio since the row's other text already conveys
    // the status.
    final textColor = switch (fine.status) {
      'paid' => const Color(0xFF39843C),
      'waived' => const Color(0xFF5C7885),
      _ => const Color(0xFFA86400),
    };

    return Card(
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: color.withValues(alpha: 0.15),
          child: Icon(Icons.receipt_outlined, color: color),
        ),
        title: Text(showStudent
            ? (fine.studentName ?? 'Student #${fine.userId}')
            : fine.violationType.replaceAll('_', ' ')),
        subtitle: Text(
          showStudent
              ? '${fine.studentIdNumber ?? ''} · ${fine.violationType.replaceAll('_', ' ')} · ₱${fine.amount.toStringAsFixed(2)}'
              : '${fine.eventTitle ?? '—'} · ₱${fine.amount.toStringAsFixed(2)}',
        ),
        trailing: fine.isUnpaid
            ? PopupMenuButton<String>(
                onSelected: (action) => _act(context, ref, action),
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 'clear', child: Text('Mark paid')),
                  PopupMenuItem(value: 'waive', child: Text('Waive')),
                ],
              )
            : Text(fine.status,
                style: TextStyle(
                    color: textColor,
                    fontSize: 11,
                    fontWeight: FontWeight.bold)),
      ),
    );
  }

  Future<void> _act(BuildContext context, WidgetRef ref, String action) async {
    final reasonController = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(
            action == 'clear' ? 'Mark this fine paid?' : 'Waive this fine?'),
        content: TextField(
          controller: reasonController,
          decoration:
              const InputDecoration(labelText: 'Reason (min. 5 characters)'),
          minLines: 2,
          maxLines: 4,
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel')),
          FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Confirm')),
        ],
      ),
    );

    if (confirmed != true || !context.mounted) return;
    if (reasonController.text.trim().length < 5) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Reason must be at least 5 characters.')));
      return;
    }

    final api = ref.read(apiClientProvider);
    try {
      await api.post('/officer/fines/${fine.id}/$action',
          data: {'reason': reasonController.text.trim()});
      onChanged();
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
              content: Text(
                  action == 'clear' ? 'Fine marked paid.' : 'Fine waived.')),
        );
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }
}
