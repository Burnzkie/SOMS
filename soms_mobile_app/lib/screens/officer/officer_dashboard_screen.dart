// lib/screens/officer/officer_dashboard_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../models/announcement.dart';
import '../../models/event.dart';
import '../../providers/auth_provider.dart';
import '../../providers/officer_providers.dart';
import 'announcements_screen.dart';
import 'calendar_screen.dart';
import 'events_screen.dart';
import 'fines_screen.dart';

/// Officer landing tab — an at-a-glance overview (live counts pulled from
/// the same endpoints the Events/Fines/Announcements tabs use, since
/// there's no dedicated GET /api/v1/officer/dashboard yet) plus the
/// upcoming event feature card and quick-access shortcuts, mirroring the
/// web officer dashboard's layout/tokens.
class OfficerDashboardScreen extends ConsumerWidget {
  const OfficerDashboardScreen({super.key});

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
    final eventsAsync = ref.watch(officerEventsProvider);
    final announcementsAsync = ref.watch(officerAnnouncementsProvider);
    final membersAsync = ref.watch(officerActiveMembersProvider);
    // Fines is Treasurer+Admin only server-side; a 403 here just means
    // "no access" for this officer, so the count silently shows as 0
    // rather than surfacing an error card on a dashboard everyone lands on.
    final finesAsync = ref.watch(officerFinesProvider('unpaid'));

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(officerEventsProvider);
        ref.invalidate(officerAnnouncementsProvider);
        ref.invalidate(officerFinesProvider);
        ref.invalidate(officerActiveMembersProvider);
      },
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('Good ${_greeting()}, ${firstName.isEmpty ? 'Officer' : firstName} 👋',
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          Text("Here's what's happening in your organization today.",
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant)),
          const SizedBox(height: 18),

          // --- Stat grid (2x2, matches the web dashboard's four tiles) --
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: 1.35,
            children: [
              eventsAsync.when(
                data: (p) => _StatTile(
                  icon: Icons.event_outlined,
                  color: Theme.of(context).colorScheme.primary,
                  value: '${p.data.where((e) => _isUpcoming(e)).length}',
                  label: 'Events',
                ),
                loading: () => const _StatTile.loading(icon: Icons.event_outlined),
                error: (_, __) => const _StatTile(icon: Icons.event_outlined, color: Colors.grey, value: '—', label: 'Events'),
              ),
              membersAsync.when(
                data: (count) => _StatTile(
                  icon: Icons.people_outline,
                  color: const Color(0xFF8B7CF6),
                  value: '$count',
                  label: 'Active Members',
                ),
                loading: () => const _StatTile.loading(icon: Icons.people_outline),
                error: (_, __) => const _StatTile(icon: Icons.people_outline, color: Colors.grey, value: '—', label: 'Active Members'),
              ),
              finesAsync.when(
                data: (p) => _StatTile(
                  icon: Icons.payments_outlined,
                  color: const Color(0xFF1FC98D),
                  value: '${p.total}',
                  label: 'Pending Fines',
                ),
                loading: () => const _StatTile.loading(icon: Icons.payments_outlined),
                error: (_, __) => const _StatTile(icon: Icons.payments_outlined, color: Colors.grey, value: '—', label: 'Pending Fines'),
              ),
              announcementsAsync.when(
                data: (r) => _StatTile(
                  icon: Icons.campaign_outlined,
                  color: const Color(0xFFF5A623),
                  value: '${r.$1.total}',
                  label: 'Updates',
                ),
                loading: () => const _StatTile.loading(icon: Icons.campaign_outlined),
                error: (_, __) => const _StatTile(icon: Icons.campaign_outlined, color: Colors.grey, value: '—', label: 'Updates'),
              ),
            ],
          ),
          const SizedBox(height: 18),

          // --- Upcoming event feature card --------------------------------
          Text('Upcoming Event', style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: Theme.of(context).colorScheme.primary, fontWeight: FontWeight.w700, letterSpacing: .4)),
          const SizedBox(height: 8),
          eventsAsync.when(
            data: (p) {
              final upcoming = p.data.where(_isUpcoming).toList()
                ..sort((a, b) => a.dateStart.compareTo(b.dateStart));
              if (upcoming.isEmpty) {
                return const _EmptyCard(text: 'No upcoming events right now.');
              }
              return _EventFeatureCard(event: upcoming.first);
            },
            loading: () => const SizedBox(height: 180, child: Center(child: CircularProgressIndicator())),
            error: (_, __) => const _EmptyCard(text: "Couldn't load events."),
          ),
          const SizedBox(height: 18),

          // --- Latest announcement ---------------------------------------
          Row(
            children: [
              Text('Latest Announcement', style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: Theme.of(context).colorScheme.primary, fontWeight: FontWeight.w700, letterSpacing: .4)),
              const Spacer(),
              TextButton(
                onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const _TabPage(title: 'Announcements', child: OfficerAnnouncementsScreen()))),
                child: const Text('View all'),
              ),
            ],
          ),
          announcementsAsync.when(
            data: (r) {
              final list = r.$1.data;
              if (list.isEmpty) return const _EmptyCard(text: 'No announcements yet.');
              return _AnnouncementCard(announcement: list.first);
            },
            loading: () => const SizedBox(height: 100, child: Center(child: CircularProgressIndicator())),
            error: (_, __) => const _EmptyCard(text: "Couldn't load announcements."),
          ),
          const SizedBox(height: 20),

          // --- Quick access -------------------------------------------------
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
                subtitle: 'Manage & view events',
                builder: (_) => const _TabPage(title: 'Events', child: OfficerEventsScreen()),
              ),
              _QuickAccessCard(
                icon: Icons.calendar_month_outlined,
                color: const Color(0xFF8B7CF6),
                title: 'Calendar',
                subtitle: 'Schedules & dates',
                builder: (_) => const _TabPage(title: 'Calendar', child: OfficerCalendarScreen()),
              ),
              _QuickAccessCard(
                icon: Icons.receipt_long_outlined,
                color: const Color(0xFF1FC98D),
                title: 'Fines',
                subtitle: 'Treasurer + Admin only',
                builder: (_) => const _TabPage(title: 'Fines', child: OfficerFinesScreen()),
              ),
              _QuickAccessCard(
                icon: Icons.campaign_outlined,
                color: const Color(0xFFF5A623),
                title: 'Announcements',
                subtitle: 'Create & manage',
                builder: (_) => const _TabPage(title: 'Announcements', child: OfficerAnnouncementsScreen()),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Card(
            color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
            child: const Padding(
              padding: EdgeInsets.all(16),
              child: Row(
                children: [
                  Icon(Icons.qr_code_scanner, size: 18),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Camera-based attendance scanning is available from a session\'s '
                      '"Scan" button under Events — including offline queueing when '
                      'there\'s no connection.',
                      style: TextStyle(fontSize: 12.5),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  static bool _isUpcoming(SomsEvent e) {
    final end = DateTime.tryParse(e.dateEnd);
    if (end == null) return true;
    final today = DateTime.now();
    return !end.isBefore(DateTime(today.year, today.month, today.day));
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({required this.icon, required this.color, required this.value, required this.label});
  const _StatTile.loading({required this.icon})
      : color = Colors.grey,
        value = '—',
        label = '';

  final IconData icon;
  final Color color;
  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 26,
              height: 26,
              decoration: BoxDecoration(color: color.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(8)),
              child: Icon(icon, size: 14, color: color),
            ),
            const SizedBox(height: 6),
            Text(value, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700), maxLines: 1, overflow: TextOverflow.ellipsis),
            Text(label, style: Theme.of(context).textTheme.bodySmall, maxLines: 1, overflow: TextOverflow.ellipsis),
          ],
        ),
      ),
    );
  }
}

class _EventFeatureCard extends StatelessWidget {
  const _EventFeatureCard({required this.event});

  final SomsEvent event;

  @override
  Widget build(BuildContext context) {
    final start = DateTime.tryParse(event.dateStart);
    final end = DateTime.tryParse(event.dateEnd);
    final dateLabel = start == null
        ? ''
        : (end != null && !_sameDay(start, end))
            ? '${_fmt(start)} – ${_fmt(end)}'
            : _fmt(start);

    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: Stack(
        children: [
          SizedBox(
            height: 220,
            width: double.infinity,
            child: event.imageUrl != null
                ? Image.network(event.imageUrl!, fit: BoxFit.cover,
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
                Text(event.title,
                    style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)),
                const SizedBox(height: 6),
                Row(children: [
                  const Icon(Icons.calendar_today, size: 13, color: Colors.white70),
                  const SizedBox(width: 6),
                  Text(dateLabel, style: const TextStyle(color: Colors.white70, fontSize: 12.5)),
                ]),
                if (event.venue != null && event.venue!.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Row(children: [
                    const Icon(Icons.place_outlined, size: 13, color: Colors.white70),
                    const SizedBox(width: 6),
                    Text(event.venue!, style: const TextStyle(color: Colors.white70, fontSize: 12.5)),
                  ]),
                ],
                const SizedBox(height: 12),
                ElevatedButton(
                  onPressed: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => const _TabPage(title: 'Events', child: OfficerEventsScreen()))),
                  child: const Text('View Event Details'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  static bool _sameDay(DateTime a, DateTime b) => a.year == b.year && a.month == b.month && a.day == b.day;
  static String _fmt(DateTime d) => '${_month[d.month - 1]} ${d.day}, ${d.year}';
  static const _month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
}

class _AnnouncementCard extends StatelessWidget {
  const _AnnouncementCard({required this.announcement});

  final SomsAnnouncement announcement;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(announcement.title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 6),
            Text(announcement.body, maxLines: 3, overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant)),
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
      height: 100,
      width: double.infinity,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        border: Border.all(color: Theme.of(context).colorScheme.outlineVariant, style: BorderStyle.solid),
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
