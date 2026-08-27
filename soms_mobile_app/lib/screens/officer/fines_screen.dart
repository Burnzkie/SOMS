import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../models/fine.dart';
import '../../providers/auth_provider.dart';
import '../../providers/officer_providers.dart';
import '../../widgets/status_views.dart';

class OfficerFinesScreen extends ConsumerStatefulWidget {
  const OfficerFinesScreen({super.key});

  @override
  ConsumerState<OfficerFinesScreen> createState() => _OfficerFinesScreenState();
}

class _OfficerFinesScreenState extends ConsumerState<OfficerFinesScreen> {
  String? _status = 'unpaid';

  @override
  Widget build(BuildContext context) {
    final fines = ref.watch(officerFinesProvider(_status));

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
          child: Wrap(
            spacing: 8,
            children: [
              _FilterChip(label: 'Unpaid', selected: _status == 'unpaid', onTap: () => setState(() => _status = 'unpaid')),
              _FilterChip(label: 'Paid', selected: _status == 'paid', onTap: () => setState(() => _status = 'paid')),
              _FilterChip(label: 'Waived', selected: _status == 'waived', onTap: () => setState(() => _status = 'waived')),
              _FilterChip(label: 'All', selected: _status == null, onTap: () => setState(() => _status = null)),
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () async => ref.invalidate(officerFinesProvider(_status)),
            child: fines.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) {
                if (e is ApiException && e.statusCode == 403) {
                  return const AccessDeniedView(
                    message: 'Fines are Treasurer + Admin only. If your org has no active '
                        'Treasurer, ask an Admin to clear/waive on your behalf.',
                  );
                }
                return ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(officerFinesProvider(_status)));
              },
              data: (page) {
                if (page.data.isEmpty) {
                  return const EmptyStateView(message: 'Nothing here.', icon: Icons.receipt_long_outlined);
                }
                return ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: page.data.length,
                  itemBuilder: (context, i) => _OfficerFineTile(
                    fine: page.data[i],
                    onChanged: () => ref.invalidate(officerFinesProvider(_status)),
                  ),
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
  const _FilterChip({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(label: Text(label), selected: selected, onSelected: (_) => onTap());
  }
}

class _OfficerFineTile extends ConsumerWidget {
  const _OfficerFineTile({required this.fine, required this.onChanged});

  final Fine fine;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final color = switch (fine.status) {
      'paid' => Colors.green,
      'waived' => Colors.blueGrey,
      _ => Colors.orange,
    };

    return Card(
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: color.withValues(alpha: 0.15),
          child: Icon(Icons.receipt_outlined, color: color),
        ),
        title: Text(fine.studentName ?? 'Student #${fine.userId}'),
        subtitle: Text(
          '${fine.studentIdNumber ?? ''} · ${fine.violationType.replaceAll('_', ' ')} · ₱${fine.amount.toStringAsFixed(2)}',
        ),
        trailing: fine.isUnpaid
            ? PopupMenuButton<String>(
                onSelected: (action) => _act(context, ref, action),
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 'clear', child: Text('Mark paid')),
                  PopupMenuItem(value: 'waive', child: Text('Waive')),
                ],
              )
            : Text(fine.status, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.bold)),
      ),
    );
  }

  Future<void> _act(BuildContext context, WidgetRef ref, String action) async {
    final reasonController = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(action == 'clear' ? 'Mark this fine paid?' : 'Waive this fine?'),
        content: TextField(
          controller: reasonController,
          decoration: const InputDecoration(labelText: 'Reason (min. 5 characters)'),
          minLines: 2,
          maxLines: 4,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Confirm')),
        ],
      ),
    );

    if (confirmed != true || !context.mounted) return;
    if (reasonController.text.trim().length < 5) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Reason must be at least 5 characters.')));
      return;
    }

    final api = ref.read(apiClientProvider);
    try {
      await api.post('/officer/fines/${fine.id}/$action', data: {'reason': reasonController.text.trim()});
      onChanged();
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(action == 'clear' ? 'Fine marked paid.' : 'Fine waived.')),
        );
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }
}
