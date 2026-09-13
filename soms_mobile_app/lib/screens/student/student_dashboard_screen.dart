// lib/screens/student/student_dashboard_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../providers/auth_provider.dart';
import '../../providers/student_providers.dart';
import '../../widgets/status_views.dart';
import 'announcements_screen.dart';
import 'events_screen.dart';
import 'fines_screen.dart';
import 'qr_screen.dart';

/// Student landing tab — mirrors the officer dashboard's stat/feature/
/// quick-access layout, but backed by the real GET /api/v1/student/dashboard
/// endpoint (a single call) rather than combining several list endpoints.
class StudentDashboardScreen extends ConsumerWidget {
  const StudentDashboardScreen({super.key});

  String _greeting() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'morning';
    if (hour < 18) return 'afternoon';
    return 'evening';
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider).user;
    final firstName = (user?.name ?? '').split(' ').first;
    final dashboard = ref.watch(studentDashboardProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(studentDashboardProvider),
      child: dashboard.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorRetryView(message: '$e', onRetry: () => ref.invalidate(studentDashboardProvider)),
        data: (data) {
          final unpaidCount = data['unpaid_fines_count'] as int? ?? 0;
          final unpaidAmount = double.tryParse('${data['unpaid_fines_amount'] ?? 0}') ?? 0;
          final events = (data['upcoming_events'] as List<dynamic>? ?? []).cast<Map<String, dynamic>>();
          final announcements = (data['recent_announcements'] as List<dynamic>? ?? []).cast<Map<String, dynamic>>();

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text('Good ${_greeting()}, ${firstName.isEmpty ? 'there' : firstName} 👋',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700)),
              const SizedBox(height: 4),
              Text("Here's what's happening in your organization today.",
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant)),
              const SizedBox(height: 18),

              if (unpaidCount > 0) ...[
                Card(
                  color: Theme.of(context).colorScheme.errorContainer,
                  child: ListTile(
                    leading: const Icon(Icons.receipt_long),
                    title: Text('$unpaidCount unpaid fine${unpaidCount == 1 ? '' : 's'} — ₱${unpaidAmount.toStringAsFixed(2)}'),
                    subtitle: const Text("Pay in person at the Treasurer's office."),
                  ),
                ),
                const SizedBox(height: 16),
              ],

              // --- Stat row ---------------------------------------------
              Row(
                children: [
                  Expanded(
                    child: _StatTile(
                      icon: Icons.event_outlined,
                      color: Theme.of(context).colorScheme.primary,
                      value: '${events.length}',
                      label: 'Events',
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _StatTile(
                      icon: Icons.payments_outlined,
                      color: const Color(0xFF1FC98D),
                      value: '$unpaidCount',
                      label: 'Unpaid Fines',
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _StatTile(
                      icon: Icons.campaign_outlined,
                      color: const Color(0xFFF5A623),
                      value: '${announcements.length}',
                      label: 'Updates',
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),

              // --- Upcoming event feature card ---------------------------
              Text('Upcoming Event', style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: Theme.of(context).colorScheme.primary, fontWeight: FontWeight.w700, letterSpacing: .4)),
              const SizedBox(height: 8),
              events.isEmpty
                  ? const _EmptyCard(text: 'Nothing scheduled right now.')
                  : _EventFeatureCard(event: events.first),
              const SizedBox(height: 18),

              // --- Latest announcement ------------------------------------
              Row(
                children: [
                  Text('Latest Announcement', style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: Theme.of(context).colorScheme.primary, fontWeight: FontWeight.w700, letterSpacing: .4)),
                  const Spacer(),
                  TextButton(
                    onPressed: () => Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const _TabPage(title: 'Announcements', child: StudentAnnouncementsScreen()))),
                    child: const Text('View all'),
                  ),
                ],
              ),
              announcements.isEmpty
                  ? const _EmptyCard(text: 'No announcements yet.')
                  : _AnnouncementCard(announcement: announcements.first),
              const SizedBox(height: 20),

              // --- Quick access -------------------------------------------
              Text('Quick Access', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 10),
              GridView.count(
                crossAxisCount: 2,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                mainAxisSpacing: 10,
                crossAxisSpacing: 10,
                childAspectRatio: 2.6,
                children: [
                  _QuickAccessCard(
                    icon: Icons.event_outlined,
                    color: Theme.of(context).colorScheme.primary,
                    title: 'Events',
                    subtitle: 'Browse events',
                    builder: (_) => const _TabPage(title: 'Events', child: StudentEventsScreen()),
                  ),
                  _QuickAccessCard(
                    icon: Icons.qr_code_2,
                    color: const Color(0xFF8B7CF6),
                    title: 'My QR',
                    subtitle: 'For attendance scanning',
                    builder: (_) => const _TabPage(title: 'My QR', child: QrScreen()),
                  ),
                  _QuickAccessCard(
                    icon: Icons.payments_outlined,
                    color: const Color(0xFF1FC98D),
                    title: 'Fines',
                    subtitle: 'Check your fine history',
                    builder: (_) => const _TabPage(title: 'Fines', child: StudentFinesScreen()),
                  ),
                  _QuickAccessCard(
                    icon: Icons.campaign_outlined,
                    color: const Color(0xFFF5A623),
                    title: 'Announcements',
                    subtitle: 'Read the latest updates',
                    builder: (_) => const _TabPage(title: 'Announcements', child: StudentAnnouncementsScreen()),
                  ),
                ],
              ),
            ],
          );
        },
      ),
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({required this.icon, required this.color, required this.value, required this.label});

  final IconData icon;
  final Color color;
  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 32,
              height: 32,
              decoration: BoxDecoration(color: color.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(9)),
              child: Icon(icon, size: 16, color: color),
            ),
            const SizedBox(height: 10),
            Text(value, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
            Text(label, style: Theme.of(context).textTheme.bodySmall, maxLines: 1, overflow: TextOverflow.ellipsis),
          ],
        ),
      ),
    );
  }
}

class _EventFeatureCard extends StatelessWidget {
  const _EventFeatureCard({required this.event});

  final Map<String, dynamic> event;

  static String _fmtRange(dynamic start, dynamic end) {
    try {
      final s = DateFormat.yMMMd().format(DateTime.parse(start as String));
      final e = DateFormat.yMMMd().format(DateTime.parse(end as String));
      return s == e ? s : '$s – $e';
    } catch (_) {
      return '';
    }
  }

  @override
  Widget build(BuildContext context) {
    final imageUrl = event['image_url'] as String?;
    final title = event['title'] as String? ?? '';
    final venue = event['venue'] as String?;

    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: Stack(
        children: [
          SizedBox(
            height: 200,
            width: double.infinity,
            child: imageUrl != null
                ? Image.network(imageUrl, fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => Container(color: Theme.of(context).colorScheme.surfaceContainerHighest))
                : Container(color: Theme.of(context).colorScheme.surfaceContainerHighest),
          ),
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.bottomCenter,
                  end: Alignment.topCenter,
                  colors: [Colors.black.withValues(alpha: 0.92), Colors.black.withValues(alpha: 0.15)],
                ),
              ),
            ),
          ),
          Positioned(
            left: 16,
            right: 16,
            bottom: 16,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)),
                const SizedBox(height: 6),
                Row(children: [
                  const Icon(Icons.calendar_today, size: 13, color: Colors.white70),
                  const SizedBox(width: 6),
                  Text(_fmtRange(event['date_start'], event['date_end']), style: const TextStyle(color: Colors.white70, fontSize: 12.5)),
                ]),
                if (venue != null && venue.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Row(children: [
                    const Icon(Icons.place_outlined, size: 13, color: Colors.white70),
                    const SizedBox(width: 6),
                    Text(venue, style: const TextStyle(color: Colors.white70, fontSize: 12.5)),
                  ]),
                ],
                const SizedBox(height: 12),
                ElevatedButton(
                  onPressed: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => const _TabPage(title: 'Events', child: StudentEventsScreen()))),
                  child: const Text('View Event Details'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _AnnouncementCard extends StatelessWidget {
  const _AnnouncementCard({required this.announcement});

  final Map<String, dynamic> announcement;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(announcement['title'] as String? ?? '',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          ],
        ),
      ),
    );
  }
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 90,
      width: double.infinity,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Text(text, style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 12.5)),
    );
  }
}

class _QuickAccessCard extends StatelessWidget {
  const _QuickAccessCard({
    required this.icon,
    required this.color,
    required this.title,
    required this.subtitle,
    required this.builder,
  });

  final IconData icon;
  final Color color;
  final String title;
  final String subtitle;
  final WidgetBuilder builder;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: builder)),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(color: color.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(9)),
                child: Icon(icon, size: 17, color: color),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13), maxLines: 1, overflow: TextOverflow.ellipsis),
                    Text(subtitle, style: const TextStyle(fontSize: 10.5), maxLines: 1, overflow: TextOverflow.ellipsis),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Wraps a tab's body with its own Scaffold + AppBar when reached via a
/// dashboard shortcut push, since the tab body alone has no app bar of
/// its own (RoleShell normally supplies one).
class _TabPage extends StatelessWidget {
  const _TabPage({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Scaffold(appBar: AppBar(title: Text(title)), body: child);
  }
}
