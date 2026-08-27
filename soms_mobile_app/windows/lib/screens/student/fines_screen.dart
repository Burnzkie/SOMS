import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../models/fine.dart';
import '../../providers/student_providers.dart';
import '../../widgets/status_views.dart';

class StudentFinesScreen extends ConsumerStatefulWidget {
  const StudentFinesScreen({super.key});

  @override
  ConsumerState<StudentFinesScreen> createState() => _StudentFinesScreenState();
}

class _StudentFinesScreenState extends ConsumerState<StudentFinesScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  static const _statuses = [null, 'unpaid', 'paid', 'waived'];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _statuses.length, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const ColoredBox(
          color: Colors.transparent,
          child: Padding(
            padding: EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Text(
              'Pay in person at the Treasurer\'s office. Fines are marked Paid here once recorded.',
              style: TextStyle(fontSize: 12),
            ),
          ),
        ),
        TabBar(
          controller: _tabController,
          tabs: const [Tab(text: 'All'), Tab(text: 'Unpaid'), Tab(text: 'Paid'), Tab(text: 'Waived')],
        ),
        Expanded(
          child: TabBarView(
            controller: _tabController,
            children: _statuses.map((status) => _FinesList(status: status)).toList(),
          ),
        ),
      ],
    );
  }
}

class _FinesList extends ConsumerWidget {
  const _FinesList({required this.status});

  final String? status;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final fines = ref.watch(studentFinesProvider(status));

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(studentFinesProvider(status)),
      child: fines.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(studentFinesProvider(status))),
        data: (page) {
          if (page.data.isEmpty) {
            return const EmptyStateView(message: 'Nothing here.', icon: Icons.receipt_long_outlined);
          }
          return ListView.builder(
            padding: const EdgeInsets.all(12),
            itemCount: page.data.length,
            itemBuilder: (context, i) => _FineTile(fine: page.data[i]),
          );
        },
      ),
    );
  }
}

class _FineTile extends StatelessWidget {
  const _FineTile({required this.fine});

  final Fine fine;

  @override
  Widget build(BuildContext context) {
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
        title: Text(fine.violationType.replaceAll('_', ' ')),
        subtitle: Text([if (fine.eventTitle != null) fine.eventTitle!, fine.reason].join(' — ')),
        trailing: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text('₱${fine.amount.toStringAsFixed(2)}', style: Theme.of(context).textTheme.titleSmall),
            Text(fine.status, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.bold)),
          ],
        ),
      ),
    );
  }
}
