import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../models/admin_models.dart';
import '../../providers/admin_providers.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/status_views.dart';

class AdminUsersScreen extends ConsumerStatefulWidget {
  const AdminUsersScreen({super.key});

  @override
  ConsumerState<AdminUsersScreen> createState() => _AdminUsersScreenState();
}

class _AdminUsersScreenState extends ConsumerState<AdminUsersScreen> {
  String? _status = 'pending';
  final _searchController = TextEditingController();
  String _search = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final filter = (status: _status, search: _search.isEmpty ? null : _search);
    final users = ref.watch(adminUsersProvider(filter));

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
          child: TextField(
            controller: _searchController,
            decoration: const InputDecoration(
              prefixIcon: Icon(Icons.search),
              hintText: 'Search by student ID or name',
              isDense: true,
              border: OutlineInputBorder(),
            ),
            onSubmitted: (v) => setState(() => _search = v.trim()),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
          child: Wrap(
            spacing: 8,
            children: [
              ChoiceChip(label: const Text('Pending'), selected: _status == 'pending', onSelected: (_) => setState(() => _status = 'pending')),
              ChoiceChip(label: const Text('Approved'), selected: _status == 'approved', onSelected: (_) => setState(() => _status = 'approved')),
              ChoiceChip(label: const Text('All'), selected: _status == null, onSelected: (_) => setState(() => _status = null)),
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () async => ref.invalidate(adminUsersProvider(filter)),
            child: users.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(adminUsersProvider(filter))),
              data: (page) {
                if (page.data.isEmpty) {
                  return const EmptyStateView(message: 'No users match.', icon: Icons.people_outline);
                }
                return ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: page.data.length,
                  itemBuilder: (context, i) => _UserTile(
                    user: page.data[i],
                    onChanged: () => ref.invalidate(adminUsersProvider(filter)),
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

class _UserTile extends ConsumerWidget {
  const _UserTile({required this.user, required this.onChanged});

  final AdminUserRow user;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Card(
      child: ListTile(
        leading: CircleAvatar(child: Text(user.name.isNotEmpty ? user.name[0].toUpperCase() : '?')),
        title: Text(user.name),
        subtitle: Text('${user.studentId} · ${user.department ?? '—'} · ${user.role}'),
        trailing: user.isApproved
            ? IconButton(
                icon: const Icon(Icons.history),
                tooltip: 'Activity log',
                onPressed: () => _showActivityLog(context, ref),
              )
            : PopupMenuButton<String>(
                onSelected: (action) => _act(context, ref, action),
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 'approve', child: Text('Approve')),
                  PopupMenuItem(value: 'reject', child: Text('Reject')),
                ],
              ),
      ),
    );
  }

  Future<void> _act(BuildContext context, WidgetRef ref, String action) async {
    String? reason;
    if (action == 'reject') {
      final controller = TextEditingController();
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Reject this registration?'),
          content: TextField(controller: controller, decoration: const InputDecoration(labelText: 'Reason (min. 5 characters)')),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Reject')),
          ],
        ),
      );
      if (confirmed != true) return;
      reason = controller.text.trim();
      if (reason.length < 5) {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Reason must be at least 5 characters.')));
        }
        return;
      }
    }

    final api = ref.read(apiClientProvider);
    try {
      await api.post('/admin/users/${user.id}/$action', data: reason != null ? {'reason': reason} : null);
      onChanged();
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(action == 'approve' ? '${user.name} approved.' : '${user.name} rejected.')),
        );
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  void _showActivityLog(BuildContext context, WidgetRef ref) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (_) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.7,
        builder: (context, scrollController) => Consumer(
          builder: (context, ref, _) {
            final log = ref.watch(userActivityLogProvider(user.id));
            return log.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(userActivityLogProvider(user.id))),
              data: (page) => ListView(
                controller: scrollController,
                padding: const EdgeInsets.all(16),
                children: [
                  Text('${user.name} — activity', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 12),
                  if (page.data.isEmpty) const Text('No activity recorded.'),
                  for (final entry in page.data)
                    ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      title: Text(entry.action.replaceAll('_', ' ')),
                      trailing: Text(entry.createdAt, style: const TextStyle(fontSize: 11)),
                    ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}
