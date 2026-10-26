// lib/screens/officer/audit_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../core/app_theme.dart';
import '../../models/audit_models.dart';
import '../../providers/audit_providers.dart';
import '../../providers/officer_providers.dart';
import '../../widgets/audit_widgets.dart';
import '../../widgets/status_views.dart';
import '../../widgets/tile_grid.dart';
import 'audit_finding_form_screen.dart';

/// The Auditor's workspace on mobile: read-only fines, payments, attendance
/// records, the discrepancy report and the activity log, plus recording and
/// reading audit findings. Nothing here changes a fine or an attendance
/// record. Each tab appears only if the officer holds its permission, and
/// the server checks it again.
class AuditScreen extends ConsumerWidget {
  const AuditScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final permissions = ref.watch(officerPermissionsProvider);

    return permissions.when(
      loading: () => _plainScaffold(const Center(child: CircularProgressIndicator())),
      error: (e, _) => _plainScaffold(
        ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(officerPermissionsProvider)),
      ),
      data: (granted) {
        final access = AuditAccess.fromPermissions(granted);

        if (!access.any) {
          return _plainScaffold(
            const AccessDeniedView(message: 'Your role has no audit permissions.'),
          );
        }

        final tabs = <({String label, Widget body})>[
          if (access.canViewReports) (label: 'Overview', body: const _OverviewTab()),
          if (access.canViewReports)
            (label: 'Discrepancies', body: _DiscrepanciesTab(canReport: access.canManageFindings)),
          if (access.canViewFines) (label: 'Fines', body: const _FinesTab()),
          if (access.canViewAttendance) (label: 'Attendance', body: const _AttendanceTab()),
          if (access.canViewFindings)
            (label: 'Findings', body: _FindingsTab(canCreate: access.canManageFindings)),
          if (access.canViewLogs) (label: 'Activity log', body: const _ActivityLogTab()),
        ];

        return DefaultTabController(
          length: tabs.length,
          child: Scaffold(
            appBar: AppBar(
              title: const Text('Audit'),
              bottom: TabBar(
                isScrollable: true,
                tabAlignment: TabAlignment.start,
                // The app bar is navy, so the tab colours must be set explicitly.
                labelColor: Colors.white,
                unselectedLabelColor: Colors.white70,
                indicatorColor: Colors.white,
                tabs: [for (final tab in tabs) Tab(text: tab.label)],
              ),
            ),
            body: TabBarView(children: [for (final tab in tabs) tab.body]),
          ),
        );
      },
    );
  }

  Widget _plainScaffold(Widget body) => Scaffold(appBar: AppBar(title: const Text('Audit')), body: body);
}

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

/// A 403 means "not your access", not a failure; anything else is retryable.
Widget _errorBody(Object error, VoidCallback onRetry, String deniedMessage) {
  if (error is ApiException && error.statusCode == 403) {
    return AccessDeniedView(message: deniedMessage);
  }
  return ErrorRetryView(message: '$error', onRetry: onRetry);
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.icon, required this.color, required this.value, required this.label});

  final IconData icon;
  final Color color;
  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: color.withValues(alpha: .12),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, size: 18, color: color),
            ),
            const SizedBox(height: 12),
            Text(
              value,
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.text),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}

/// Simple "label ... value" row used in the overview list.
class _CountRow extends StatelessWidget {
  const _CountRow(this.label, this.value, {this.color});

  final String label;
  final String value;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Expanded(child: Text(label, style: const TextStyle(fontSize: 13.5))),
          Text(
            value,
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: color ?? AppColors.text),
          ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Overview
// ---------------------------------------------------------------------------

class _OverviewTab extends ConsumerWidget {
  const _OverviewTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final summary = ref.watch(auditSummaryProvider);
    final discrepancies = ref.watch(auditDiscrepanciesProvider);

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(auditSummaryProvider);
        ref.invalidate(auditDiscrepanciesProvider);
      },
      child: summary.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => _errorBody(
          e,
          () => ref.invalidate(auditSummaryProvider),
          'Only officers with the View Reports permission can open the overview.',
        ),
        data: (s) {
          final bySeverity = discrepancies.maybeWhen(
            data: (list) => {
              for (final level in const ['high', 'medium', 'low'])
                level: list.where((d) => d.severity == level).length,
            },
            orElse: () => <String, int>{},
          );

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              TileGrid(
                columns: 2,
                children: [
                  _StatCard(
                    icon: Icons.receipt_long_outlined,
                    color: AppColors.amber,
                    value: formatPeso(s.assessedTotal),
                    label: 'Fines assessed (${s.assessedCount})',
                  ),
                  _StatCard(
                    icon: Icons.payments_outlined,
                    color: AppColors.emerald,
                    value: formatPeso(s.collectedTotal),
                    label: 'Collections recorded (${s.collectedCount} paid)',
                  ),
                  _StatCard(
                    icon: Icons.hourglass_bottom_outlined,
                    color: AppColors.rose,
                    value: formatPeso(s.outstandingTotal),
                    label: 'Outstanding (${s.outstandingCount} unpaid)',
                  ),
                  _StatCard(
                    icon: Icons.handshake_outlined,
                    color: AppColors.purple,
                    value: formatPeso(s.waivedTotal),
                    label: 'Waived (${s.waivedCount})',
                  ),
                ],
              ),
              const SizedBox(height: 18),
              Card(
                margin: EdgeInsets.zero,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Records requiring review',
                        style: Theme.of(context)
                            .textTheme
                            .titleSmall
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 6),
                      if (bySeverity.isEmpty)
                        const _CountRow('Discrepancies', '…')
                      else ...[
                        _CountRow('High-severity discrepancies', '${bySeverity['high']}',
                            color: severityColor('high')),
                        _CountRow('Medium-severity discrepancies', '${bySeverity['medium']}',
                            color: severityColor('medium')),
                        _CountRow('Low-severity discrepancies', '${bySeverity['low']}'),
                      ],
                      _CountRow('Flagged scans waiting for a decision', '${s.pendingFlagged}'),
                      _CountRow('Findings still open or acknowledged', '${s.activeFindings}'),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),
              const Text(
                'Read-only. Problems are reported as findings; nothing here edits a record.',
                style: TextStyle(fontSize: 12, color: AppColors.textMuted),
              ),
            ],
          );
        },
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Discrepancies
// ---------------------------------------------------------------------------

class _DiscrepanciesTab extends ConsumerWidget {
  const _DiscrepanciesTab({required this.canReport});

  final bool canReport;

  /// Opens the finding form pre-filled from the discrepancy.
  Future<void> _report(BuildContext context, AuditDiscrepancy d) {
    final draft = AuditFindingDraft(
      referenceType: auditReferenceTypes.contains(d.referenceType) ? d.referenceType : 'general',
      referenceId: d.referenceId,
      title: clipText('${d.checkLabel} — ${d.student}', 160),
      issue: clipText(d.summary, 2000),
      severity: d.severity,
    );

    return Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => AuditFindingFormScreen(initial: draft)),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final discrepancies = ref.watch(auditDiscrepanciesProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(auditDiscrepanciesProvider),
      child: discrepancies.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => _errorBody(
          e,
          () => ref.invalidate(auditDiscrepanciesProvider),
          'Only officers with the View Reports permission can open the discrepancy report.',
        ),
        data: (list) {
          if (list.isEmpty) {
            return const EmptyStateView(
              message: 'No discrepancies found. Records are consistent.',
              icon: Icons.verified_outlined,
            );
          }

          return ListView.builder(
            padding: const EdgeInsets.all(12),
            itemCount: list.length,
            itemBuilder: (context, i) {
              final d = list[i];

              return Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Wrap(
                        spacing: 8,
                        runSpacing: 6,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          AuditPill(capitalize(d.severity), severityColor(d.severity)),
                          Text(d.checkLabel, style: const TextStyle(fontWeight: FontWeight.w700)),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(d.student, style: const TextStyle(fontSize: 13)),
                      if (d.event != null)
                        Text(d.event!, style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
                      const SizedBox(height: 6),
                      Text(d.summary, style: const TextStyle(fontSize: 12.5)),
                      if (canReport)
                        Align(
                          alignment: Alignment.centerRight,
                          child: TextButton(
                            onPressed: () => _report(context, d),
                            child: const Text('Report as finding'),
                          ),
                        ),
                    ],
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

// ---------------------------------------------------------------------------
// Fines (read-only)
// ---------------------------------------------------------------------------

class _FinesTab extends ConsumerStatefulWidget {
  const _FinesTab();

  @override
  ConsumerState<_FinesTab> createState() => _FinesTabState();
}

class _FinesTabState extends ConsumerState<_FinesTab> {
  final _searchController = TextEditingController();
  String _status = '';
  String _search = '';
  int _page = 1;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  AuditFineQuery get _query => (status: _status, search: _search, page: _page);

  /// Any filter change goes back to the first page.
  void _setStatus(String status) => setState(() {
        _status = status;
        _page = 1;
      });

  void _submitSearch(String value) => setState(() {
        _search = value.trim();
        _page = 1;
      });

  @override
  Widget build(BuildContext context) {
    final fines = ref.watch(auditFinesProvider(_query));

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: Column(
            children: [
              TextField(
                controller: _searchController,
                textInputAction: TextInputAction.search,
                onSubmitted: _submitSearch,
                decoration: InputDecoration(
                  hintText: 'Student name, ID or receipt no.',
                  prefixIcon: const Icon(Icons.search),
                  suffixIcon: _search.isEmpty
                      ? null
                      : IconButton(
                          icon: const Icon(Icons.clear),
                          tooltip: 'Clear search',
                          onPressed: () {
                            _searchController.clear();
                            _submitSearch('');
                          },
                        ),
                ),
              ),
              const SizedBox(height: 8),
              Align(
                alignment: Alignment.centerLeft,
                child: Wrap(
                  spacing: 8,
                  children: [
                    for (final status in const ['', 'unpaid', 'paid', 'waived'])
                      ChoiceChip(
                        label: Text(status.isEmpty ? 'All' : capitalize(status)),
                        selected: _status == status,
                        onSelected: (_) => _setStatus(status),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () async => ref.invalidate(auditFinesProvider(_query)),
            child: fines.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => _errorBody(
                e,
                () => ref.invalidate(auditFinesProvider(_query)),
                'Only officers with the View Fines permission can open this list.',
              ),
              data: (page) {
                if (page.data.isEmpty) {
                  return const EmptyStateView(message: 'No fines match.', icon: Icons.receipt_long_outlined);
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
                    return _FineCard(fine: page.data[i]);
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

class _FineCard extends StatelessWidget {
  const _FineCard({required this.fine});

  final AuditFineRow fine;

  @override
  Widget build(BuildContext context) {
    final settled = fine.settledBy == null
        ? null
        : 'Settled by ${fine.settledBy} · ${formatDay(fine.settledAt)}';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    fine.studentName,
                    style: const TextStyle(fontWeight: FontWeight.w700),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                AuditPill(capitalize(fine.status), fineStatusColor(fine.status)),
              ],
            ),
            Text(fine.studentId, style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
            const SizedBox(height: 8),
            Text(
              '${formatPeso(fine.amount)} · ${humanize(fine.violationType)}',
              style: const TextStyle(fontSize: 13),
            ),
            if (fine.event != null)
              Text(fine.event!, style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
            if (settled != null) Text(settled, style: const TextStyle(fontSize: 12)),
            if (fine.receiptNumber != null)
              Text('Receipt ${fine.receiptNumber}', style: const TextStyle(fontSize: 12)),
            if (fine.status != 'unpaid' && (fine.reason ?? '').isNotEmpty)
              Text(fine.reason!, style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
          ],
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Findings
// ---------------------------------------------------------------------------

class _FindingsTab extends ConsumerStatefulWidget {
  const _FindingsTab({required this.canCreate});

  final bool canCreate;

  @override
  ConsumerState<_FindingsTab> createState() => _FindingsTabState();
}

class _FindingsTabState extends ConsumerState<_FindingsTab> {
  String _status = '';
  int _page = 1;

  AuditFindingQuery get _query => (status: _status, page: _page);

  void _setStatus(String status) => setState(() {
        _status = status;
        _page = 1;
      });

  Future<void> _openForm() => Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const AuditFindingFormScreen()),
      );

  void _showDetail(AuditFinding finding) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => AuditFindingDetail(finding: finding),
    );
  }

  @override
  Widget build(BuildContext context) {
    final findings = ref.watch(auditFindingsProvider(_query));

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: Column(
            children: [
              if (widget.canCreate)
                Align(
                  alignment: Alignment.centerRight,
                  child: FilledButton.icon(
                    onPressed: _openForm,
                    icon: const Icon(Icons.add),
                    label: const Text('New finding'),
                  ),
                ),
              Align(
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
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () async => ref.invalidate(auditFindingsProvider(_query)),
            child: findings.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => _errorBody(
                e,
                () => ref.invalidate(auditFindingsProvider(_query)),
                'Only officers with an audit permission can read findings.',
              ),
              data: (page) {
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
    );
  }
}

// ---------------------------------------------------------------------------
// Attendance (read-only)
// ---------------------------------------------------------------------------

class _AttendanceTab extends ConsumerStatefulWidget {
  const _AttendanceTab();

  @override
  ConsumerState<_AttendanceTab> createState() => _AttendanceTabState();
}

class _AttendanceTabState extends ConsumerState<_AttendanceTab> {
  static const _sections = ['Sessions', 'Flagged scans', 'Manual overrides'];

  int _section = 0;
  int _page = 1;

  @override
  Widget build(BuildContext context) {
    final attendance = ref.watch(auditAttendanceProvider(_page));

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: Align(
            alignment: Alignment.centerLeft,
            child: Wrap(
              spacing: 8,
              children: [
                for (var i = 0; i < _sections.length; i++)
                  ChoiceChip(
                    label: Text(_sections[i]),
                    selected: _section == i,
                    onSelected: (_) => setState(() => _section = i),
                  ),
              ],
            ),
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () async => ref.invalidate(auditAttendanceProvider(_page)),
            child: attendance.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => _errorBody(
                e,
                () => ref.invalidate(auditAttendanceProvider(_page)),
                'Only officers with the View Attendance permission can open this.',
              ),
              data: (data) => switch (_section) {
                0 => _sessions(data),
                1 => _flagged(data),
                _ => _overrides(data),
              },
            ),
          ),
        ),
      ],
    );
  }

  Widget _sessions(AuditAttendance data) {
    final page = data.sessions;
    if (page.data.isEmpty) {
      return const EmptyStateView(message: 'No sessions yet.', icon: Icons.event_note_outlined);
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

        final row = page.data[i];
        return Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(row.event, style: const TextStyle(fontWeight: FontWeight.w700)),
                const SizedBox(height: 2),
                Text(
                  '${row.session} · ${formatDateTime(row.timeinStart)}',
                  style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    AuditPill('Present ${row.present}', AppColors.emeraldText),
                    AuditPill(
                      'Flagged ${row.pendingFlagged}',
                      row.pendingFlagged > 0 ? AppColors.amberText : AppColors.textMuted,
                    ),
                    AuditPill('Approved ${row.approved}', AppColors.primary),
                    AuditPill('Rejected ${row.rejected}', AppColors.roseText),
                    AuditPill('Overrides ${row.overrides}', AppColors.textMuted),
                    AuditPill('Fines ${row.fines}', AppColors.roseText),
                  ],
                ),
                if (!row.finesIssued)
                  const Padding(
                    padding: EdgeInsets.only(top: 8),
                    child: Text('Fines not issued yet', style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _flagged(AuditAttendance data) {
    if (data.flaggedHistory.isEmpty) {
      return const EmptyStateView(message: 'No flagged scans.', icon: Icons.flag_outlined);
    }

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(12, 4, 12, 12),
      itemCount: data.flaggedHistory.length,
      itemBuilder: (context, i) {
        final scan = data.flaggedHistory[i];
        final (label, color) = switch (scan.decision) {
          'approved' => ('Approved', AppColors.emeraldText),
          'rejected' => ('Rejected', AppColors.roseText),
          _ => ('Pending', AppColors.amberText),
        };

        return Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(scan.studentName, style: const TextStyle(fontWeight: FontWeight.w700))),
                    AuditPill(label, color),
                  ],
                ),
                Text(scan.studentId, style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
                const SizedBox(height: 6),
                Text(
                  '${scan.event} · ${scan.session} · ${scan.scanType == 'time_out' ? 'Time out' : 'Time in'}',
                  style: const TextStyle(fontSize: 12.5),
                ),
                Text(
                  'Device clock ${formatDateTime(scan.deviceScannedAt)} · received ${formatDateTime(scan.scannedAt)}',
                  style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                ),
                if (scan.reviewedBy != null)
                  Text(
                    'Reviewed by ${scan.reviewedBy} · ${formatDateTime(scan.reviewedAt)}',
                    style: const TextStyle(fontSize: 12),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _overrides(AuditAttendance data) {
    if (data.overrides.isEmpty) {
      return const EmptyStateView(message: 'No manual overrides.', icon: Icons.edit_note_outlined);
    }

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(12, 4, 12, 12),
      itemCount: data.overrides.length,
      itemBuilder: (context, i) {
        final row = data.overrides[i];

        return Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(row.studentName, style: const TextStyle(fontWeight: FontWeight.w700)),
                Text(row.studentId, style: const TextStyle(fontSize: 12, color: AppColors.textMuted)),
                const SizedBox(height: 6),
                Text(
                  '${row.event} · ${row.session} · ${row.scanType == 'time_out' ? 'Time out' : 'Time in'}',
                  style: const TextStyle(fontSize: 12.5),
                ),
                Text(
                  'Marked by ${row.markedBy ?? '—'} · ${formatDateTime(row.scannedAt)}',
                  style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                ),
                if ((row.reason ?? '').isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(row.reason!, style: const TextStyle(fontSize: 12.5)),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }
}

// ---------------------------------------------------------------------------
// Activity log (read-only)
// ---------------------------------------------------------------------------

class _ActivityLogTab extends ConsumerStatefulWidget {
  const _ActivityLogTab();

  @override
  ConsumerState<_ActivityLogTab> createState() => _ActivityLogTabState();
}

class _ActivityLogTabState extends ConsumerState<_ActivityLogTab> {
  String _action = '';
  int _page = 1;

  AuditLogQuery get _query => (action: _action, page: _page);

  void _setAction(String action) => setState(() {
        _action = action;
        _page = 1;
      });

  @override
  Widget build(BuildContext context) {
    final log = ref.watch(auditActivityLogProvider(_query));

    // The filter's options come with the response; keep the picker usable
    // (and the chosen value valid) while a new filter is loading.
    final actions = log.valueOrNull?.actions ?? const <String>[];
    final options = <String>{'', _action, ...actions}.toList();

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: DropdownButton<String>(
            value: _action,
            isExpanded: true,
            items: [
              for (final option in options)
                DropdownMenuItem(value: option, child: Text(option.isEmpty ? 'All actions' : option)),
            ],
            onChanged: (value) => _setAction(value ?? ''),
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () async => ref.invalidate(auditActivityLogProvider(_query)),
            child: log.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => _errorBody(
                e,
                () => ref.invalidate(auditActivityLogProvider(_query)),
                'Only officers with the View Audit Logs permission can open this.',
              ),
              data: (data) {
                if (data.logs.data.isEmpty) {
                  return const EmptyStateView(message: 'No activity logs match.', icon: Icons.receipt_long_outlined);
                }

                return ListView.builder(
                  padding: const EdgeInsets.fromLTRB(12, 4, 12, 12),
                  itemCount: data.logs.data.length + 2,
                  itemBuilder: (context, i) {
                    if (i == 0) {
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: data.chainOk
                            ? const Text(
                                'Hash chain verified. IP addresses are masked.',
                                style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                              )
                            : Text(
                                '⚠ The activity log hash chain failed verification. Report this to the Admin immediately.',
                                style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: severityColor('high')),
                              ),
                      );
                    }
                    if (i == data.logs.data.length + 1) {
                      return AuditPager(
                        page: data.logs.currentPage,
                        lastPage: data.logs.lastPage,
                        onPage: (p) => setState(() => _page = p),
                      );
                    }

                    final row = data.logs.data[i - 1];
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(14),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(row.action, style: const TextStyle(fontWeight: FontWeight.w700)),
                            const SizedBox(height: 2),
                            Text(
                              '${formatDateTime(row.createdAt)} · ${row.user}',
                              style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                            ),
                            if (row.record != null) Text(row.record!, style: const TextStyle(fontSize: 12.5)),
                            if (row.details != null)
                              Text(
                                row.details!,
                                maxLines: 3,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(fontSize: 11.5, color: AppColors.textMuted),
                              ),
                            if (row.ip != null)
                              Text('IP ${row.ip}', style: const TextStyle(fontSize: 11.5, color: AppColors.textMuted)),
                          ],
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
    );
  }
}
