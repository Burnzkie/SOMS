import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../providers/student_providers.dart';
import '../../widgets/status_views.dart';

class StudentAnnouncementsScreen extends ConsumerWidget {
  const StudentAnnouncementsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final announcements = ref.watch(studentAnnouncementsProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(studentAnnouncementsProvider),
      child: announcements.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) =>
            ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(studentAnnouncementsProvider)),
        data: (list) {
          if (list.isEmpty) {
            return const EmptyStateView(message: 'No announcements yet.', icon: Icons.campaign_outlined);
          }
          return ListView.builder(
            padding: const EdgeInsets.all(12),
            itemCount: list.length,
            itemBuilder: (context, i) {
              final a = list[i];
              return Card(
                child: ListTile(
                  leading: const Icon(Icons.campaign_outlined),
                  title: Text(a.title),
                  subtitle: Text(
                    a.body,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  onTap: () => showModalBottomSheet(
                    context: context,
                    isScrollControlled: true,
                    builder: (_) => DraggableScrollableSheet(
                      expand: false,
                      initialChildSize: 0.6,
                      builder: (context, scrollController) => ListView(
                        controller: scrollController,
                        padding: const EdgeInsets.all(20),
                        children: [
                          Text(a.title, style: Theme.of(context).textTheme.titleLarge),
                          const SizedBox(height: 12),
                          Text(a.body),
                        ],
                      ),
                    ),
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
