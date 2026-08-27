import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../models/announcement.dart';
import '../../providers/auth_provider.dart';
import '../../providers/officer_providers.dart';
import '../../widgets/status_views.dart';

class OfficerAnnouncementsScreen extends ConsumerWidget {
  const OfficerAnnouncementsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final result = ref.watch(officerAnnouncementsProvider);

    return Scaffold(
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openComposer(context, ref),
        icon: const Icon(Icons.add),
        label: const Text('Draft'),
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(officerAnnouncementsProvider),
        child: result.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) {
            if (e is ApiException && e.statusCode == 403) {
              return const AccessDeniedView(message: 'You don\'t have access to draft announcements.');
            }
            return ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(officerAnnouncementsProvider));
          },
          data: (data) {
            final (page, canPublish) = data;
            if (page.data.isEmpty) {
              return const EmptyStateView(message: 'No drafts yet.', icon: Icons.campaign_outlined);
            }
            return ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: page.data.length,
              itemBuilder: (context, i) => _AnnouncementTile(
                announcement: page.data[i],
                canPublish: canPublish,
                onChanged: () => ref.invalidate(officerAnnouncementsProvider),
              ),
            );
          },
        ),
      ),
    );
  }

  Future<void> _openComposer(BuildContext context, WidgetRef ref) async {
    final titleController = TextEditingController();
    final bodyController = TextEditingController();

    final submitted = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          top: 20,
          bottom: MediaQuery.of(ctx).viewInsets.bottom + 20,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('New draft', style: Theme.of(ctx).textTheme.titleLarge),
            const SizedBox(height: 16),
            TextField(controller: titleController, decoration: const InputDecoration(labelText: 'Title')),
            const SizedBox(height: 12),
            TextField(
              controller: bodyController,
              decoration: const InputDecoration(labelText: 'Body'),
              minLines: 4,
              maxLines: 8,
            ),
            const SizedBox(height: 16),
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save draft')),
          ],
        ),
      ),
    );

    if (submitted != true || !context.mounted) return;
    if (titleController.text.trim().isEmpty || bodyController.text.trim().isEmpty) return;

    final api = ref.read(apiClientProvider);
    try {
      await api.post('/officer/announcements', data: {
        'title': titleController.text.trim(),
        'body': bodyController.text.trim(),
      });
      ref.invalidate(officerAnnouncementsProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Draft saved. An Executive officer must publish it.')),
        );
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }
}

class _AnnouncementTile extends ConsumerWidget {
  const _AnnouncementTile({required this.announcement, required this.canPublish, required this.onChanged});

  final SomsAnnouncement announcement;
  final bool canPublish;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Card(
      child: ListTile(
        leading: Icon(announcement.isPublished ? Icons.campaign : Icons.edit_note),
        title: Text(announcement.title),
        subtitle: Text(
          announcement.body,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
        isThreeLine: true,
        trailing: canPublish
            ? TextButton(
                onPressed: () => _togglePublish(context, ref),
                child: Text(announcement.isPublished ? 'Unpublish' : 'Publish'),
              )
            : Text(
                announcement.isPublished ? 'Published' : 'Draft',
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                  color: announcement.isPublished ? Colors.green : Colors.orange,
                ),
              ),
      ),
    );
  }

  Future<void> _togglePublish(BuildContext context, WidgetRef ref) async {
    final api = ref.read(apiClientProvider);
    final action = announcement.isPublished ? 'unpublish' : 'publish';
    try {
      await api.post('/officer/announcements/${announcement.id}/$action');
      onChanged();
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }
}
