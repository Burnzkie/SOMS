import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../models/admin_models.dart';
import '../../providers/admin_providers.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/status_views.dart';

class OfficerAppointmentScreen extends ConsumerWidget {
  const OfficerAppointmentScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final panel = ref.watch(officerAppointmentPanelProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(officerAppointmentPanelProvider),
      child: panel.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(officerAppointmentPanelProvider)),
        data: (data) {
          final (positions, students, academicYear) = data;
          return ListView(
            padding: const EdgeInsets.all(12),
            children: [
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
                child: Text('Academic year $academicYear', style: Theme.of(context).textTheme.labelLarge),
              ),
              for (final row in positions)
                Card(
                  child: ListTile(
                    leading: Icon(row.vacant ? Icons.person_off_outlined : Icons.badge_outlined),
                    title: Text(row.position),
                    subtitle: Text(row.vacant ? 'Vacant' : '${row.officerName} · ${row.officerStudentId}'),
                    trailing: row.vacant
                        ? FilledButton.tonal(
                            onPressed: () => _openAppointSheet(context, ref, row.position, students, academicYear),
                            child: const Text('Appoint'),
                          )
                        : OutlinedButton(
                            onPressed: () => _confirmRevoke(context, ref, row),
                            child: const Text('Revoke'),
                          ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _openAppointSheet(
    BuildContext context,
    WidgetRef ref,
    String position,
    List<ApprovedStudent> students,
    String academicYear,
  ) async {
    ApprovedStudent? selected;

    final confirmed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) => Padding(
          padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: MediaQuery.of(ctx).viewInsets.bottom + 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Appoint $position', style: Theme.of(ctx).textTheme.titleLarge),
              const SizedBox(height: 16),
              DropdownButtonFormField<ApprovedStudent>(
                initialValue: selected,
                decoration: const InputDecoration(labelText: 'Approved student'),
                items: students
                    .map((s) => DropdownMenuItem(value: s, child: Text('${s.name} (${s.studentId})')))
                    .toList(),
                onChanged: (v) => setSheetState(() => selected = v),
              ),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: selected == null ? null : () => Navigator.pop(ctx, true),
                child: const Text('Confirm appointment'),
              ),
            ],
          ),
        ),
      ),
    );

    if (confirmed != true || selected == null || !context.mounted) return;

    final api = ref.read(apiClientProvider);
    try {
      await api.post('/admin/officers/appoint', data: {
        'user_id': selected!.id,
        'position_title': position,
        'academic_year': academicYear,
      });
      ref.invalidate(officerAppointmentPanelProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${selected!.name} appointed as $position.')),
        );
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Future<void> _confirmRevoke(BuildContext context, WidgetRef ref, OfficerPanelRow row) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Revoke ${row.officerName}?'),
        content: Text('${row.position} reverts to vacant and ${row.officerName}\'s role reverts to student.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Revoke')),
        ],
      ),
    );

    if (confirmed != true || row.positionId == null || !context.mounted) return;

    final api = ref.read(apiClientProvider);
    try {
      // Uses the officer_positions row id (position_id), not the user id
      // -- see Api\Admin\OfficerAppointmentController::index, fixed
      // alongside this screen to actually expose that id.
      await api.post('/admin/officers/${row.positionId}/revoke');
      ref.invalidate(officerAppointmentPanelProvider);
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }
}
