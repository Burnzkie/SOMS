// lib/screens/admin/admin_findings_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../core/app_theme.dart';
import '../../models/audit_models.dart';
import '../../providers/admin_providers.dart';
import '../../providers/audit_providers.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/audit_widgets.dart';
import '../../widgets/status_views.dart';

/// Audit findings reported by the Auditor, for the Admin to act on:
/// acknowledge (received, being handled) or resolve (with a note on what
/// was done). The Admin never edits the record a finding is about. If the
/// problem is still there afterwards, the Auditor reopens the finding on
/// the web.
class AdminFindingsScreen extends ConsumerStatefulWidget {
  const AdminFindingsScreen({super.key});

  @override
  ConsumerState<AdminFindingsScreen> createState() => _AdminFindingsScreenState();
}

class _AdminFindingsScreenState extends ConsumerState<AdminFindingsScreen> {
  String _status = '';
  int _page = 1;

  AuditFindingQuery get _query => (status: _status, page: _page);

  void _setStatus(String status) => setState(() {
        _status = status;
        _page = 1;
      });

  void _showDetail(AuditFinding finding) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => AuditFindingDetail(finding: finding, footer: _FindingActions(finding: finding)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final findings = ref.watch(adminFindingsProvider(_query));

    return Scaffold(
      appBar: AppBar(title: const Text('Audit findings')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Wrap(
                spacing: 8,
                children: [
                  for (final status in const ['', 'open', 'acknowledged', 'resolved', 'dismissed'])
                    ChoiceChip(
                      label: Text(status.isEmpty ? 'All' : capitalize(status)),
                      selected: _status == status,
                      onSelected: (_) => _setStatus(status),
                    ),
                ],
              ),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () async => ref.invalidate(adminFindingsProvider(_query)),
              child: findings.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (e, _) => e is ApiException && e.statusCode == 403
                    ? const AccessDeniedView(message: 'Only the Admin can respond to audit findings.')
                    : ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(adminFindingsProvider(_query))),
                data: (result) {
                  final page = result.$1;
                  if (page.data.isEmpty) {
                    return const EmptyStateView(message: 'No findings.', icon: Icons.fact_check_outlined);
                  }

                  return ListView.builder(
                    padding: const EdgeInsets.fromLTRB(12, 4, 12, 12),
                    itemCount: page.data.length + 1,
                    itemBuilder: (context, i) {
                      if (i == page.data.length) {
                        return AuditPager(
                          page: page.currentPage,
                          lastPage: page.lastPage,
                          onPage: (p) => setState(() => _page = p),
                        );
                      }

                      final finding = page.data[i];
                      return Card(
                        child: InkWell(
                          borderRadius: BorderRadius.circular(AppTheme.radiusLg),
                          onTap: () => _showDetail(finding),
                          child: Padding(
                            padding: const EdgeInsets.all(14),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Wrap(
                                  spacing: 8,
                                  runSpacing: 6,
                                  children: [
                                    AuditPill(capitalize(finding.severity), severityColor(finding.severity)),
                                    AuditPill(capitalize(finding.status), findingStatusColor(finding.status)),
                                  ],
                                ),
                                const SizedBox(height: 8),
                                Text(finding.title, style: const TextStyle(fontWeight: FontWeight.w700)),
                                const SizedBox(height: 4),
                                Text(
                                  '#${finding.id} · ${formatDay(finding.reportedAt)}'
                                  '${finding.reporterName == null ? '' : ' · ${finding.reporterName}'}',
                                  style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Acknowledge / resolve buttons in the finding's detail sheet.
class _FindingActions extends ConsumerStatefulWidget {
  const _FindingActions({required this.finding});

  final AuditFinding finding;

  @override
  ConsumerState<_FindingActions> createState() => _FindingActionsState();
}

class _FindingActionsState extends ConsumerState<_FindingActions> {
  // Disables the buttons while a request is in flight so a double-tap can't send twice.
  bool _busy = false;

  /// Asks for the note the server requires when resolving (at least 5 characters).
  Future<String?> _askResolutionNote() {
    final controller = TextEditingController();
    String? error;

    return showDialog<String>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          title: const Text('Resolve this finding'),
          content: TextField(
            controller: controller,
            maxLength: 1000,
            minLines: 2,
            maxLines: 5,
            autofocus: true,
            decoration: InputDecoration(labelText: 'What was done to resolve it?', errorText: error),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
            FilledButton(
              onPressed: () {
                final note = controller.text.trim();
                if (note.length < 5) {
                  setDialogState(() => error = 'Please write at least 5 characters.');
                  return;
                }
                Navigator.pop(ctx, note);
              },
              child: const Text('Mark resolved'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _respond(String action) async {
    String? note;
    if (action == 'resolve') {
      note = await _askResolutionNote();
      if (note == null || !mounted) return;
    }

    setState(() => _busy = true);
    final api = ref.read(apiClientProvider);
    final messenger = ScaffoldMessenger.of(context);

    try {
      final res = await api.post('/admin/audit-findings/${widget.finding.id}/respond', data: {
        'action': action,
        if (note != null) 'resolution_note': note,
      });

      ref.invalidate(adminFindingsProvider);
      if (!mounted) return;
      Navigator.of(context).pop(); // close the detail sheet
      messenger.showSnackBar(SnackBar(content: Text(res['message'] as String? ?? 'Done.')));
    } on ApiException catch (e) {
      // 422 = it was already handled elsewhere; refresh so the list shows its real status.
      if (e.statusCode == 422) ref.invalidate(adminFindingsProvider);
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final status = widget.finding.status;

    if (status != 'open' && status != 'acknowledged') {
      return const Text(
        'This finding is closed. The Auditor can reopen it if the problem is still there.',
        style: TextStyle(fontSize: 12, color: AppColors.textMuted),
      );
    }

    return Row(
      children: [
        if (status == 'open')
          Expanded(
            child: OutlinedButton(
              onPressed: _busy ? null : () => _respond('acknowledge'),
              child: const Text('Acknowledge'),
            ),
          ),
        if (status == 'open') const SizedBox(width: 8),
        Expanded(
          child: FilledButton(
            onPressed: _busy ? null : () => _respond('resolve'),
            child: _busy
                ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Resolve'),
          ),
        ),
      ],
    );
  }
}
