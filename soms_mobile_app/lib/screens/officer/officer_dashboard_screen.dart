// lib/screens/officer/officer_dashboard_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/app_theme.dart';
import '../../models/announcement.dart';
import '../../models/audit_models.dart';
import '../../models/event.dart';
import '../../providers/auth_provider.dart';
import '../../providers/officer_providers.dart';
import '../../widgets/tile_grid.dart';
import 'announcements_screen.dart';
import 'audit_screen.dart';
import 'calendar_screen.dart';
import 'events_screen.dart';
import 'fines_screen.dart';

/// Officer landing tab — an at-a-glance overview (live counts pulled from
/// the same endpoints the Events/Fines/Announcements tabs use, since
/// there's no dedicated GET /api/v1/officer/dashboard yet) plus the
/// upcoming event hero card and quick-access shortcuts, styled to match
/// the web officer dashboard and the SOMS mobile mockup.
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
    // "no access" for this officer, so the count silently shows as — rather
    // than surfacing an error card on a dashboard everyone lands on.
    final finesAsync = ref.watch(officerFinesProvider('unpaid'));
    // The login response carries no permissions, so the server is asked; the
    // Audit card only appears for officers who hold an audit permission.
    final hasAudit = ref.watch(officerPermissionsProvider).maybeWhen(
          data: (granted) => AuditAccess.fromPermissions(granted).any,
          orElse: () => false,
        );

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(officerEventsProvider);
        ref.invalidate(officerAnnouncementsProvider);
        ref.invalidate(officerFinesProvider);
        ref.invalidate(officerActiveMembersProvider);
        ref.invalidate(officerPermissionsProvider);
      },
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Good ${_greeting()}, ${firstName.isEmpty ? 'Officer' : firstName}!',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  letterSpacing: -.3,
                  color: AppColors.text,
                ),
          ),
          const SizedBox(height: 4),
          const Text(
            "Here's what's happening in your organization today.",
            style: TextStyle(fontSize: 13, color: AppColors.textMuted),
          ),
          const SizedBox(height: 18),

          // --- Stat grid (2x2, same four tiles as the web dashboard) ------
          TileGrid(
            columns: 2,
            children: [
              eventsAsync.when(
                data: (p) => _StatTile(
                  icon: Icons.event_outlined,
                  color: AppColors.primary,
                  value: '${p.data.where((e) => _isUpcoming(e)).length}',
                  label: 'Upcoming Events',
                ),
                loading: () =>
                    const _StatTile.loading(icon: Icons.event_outlined),
                error: (_, __) => const _StatTile(
                    icon: Icons.event_outlined,
                    color: Colors.grey,
                    value: '—',
                    label: 'Upcoming Events'),
              ),
              membersAsync.when(
                data: (count) => _StatTile(
                  icon: Icons.people_outline,
                  color: AppColors.purple,
                  value: '$count',
                  label: 'Active Members',
                ),
                loading: () =>
                    const _StatTile.loading(icon: Icons.people_outline),
                error: (_, __) => const _StatTile(
                    icon: Icons.people_outline,
                    color: Colors.grey,
                    value: '—',
                    label: 'Active Members'),
              ),
              finesAsync.when(
                data: (p) => _StatTile(
                  icon: Icons.payments_outlined,
                  color: AppColors.emerald,
                  value: '${p.total}',
                  label: 'Pending Fines',
                ),
                loading: () =>
                    const _StatTile.loading(icon: Icons.payments_outlined),
                error: (_, __) => const _StatTile(
                    icon: Icons.payments_outlined,
                    color: Colors.grey,
                    value: '—',
                    label: 'Pending Fines'),
              ),
              announcementsAsync.when(
                data: (r) => _StatTile(
                  icon: Icons.campaign_outlined,
                  color: AppColors.amber,
                  value: '${r.$1.total}',
                  label: 'Updates',
                ),
                loading: () =>
                    const _StatTile.loading(icon: Icons.campaign_outlined),
                error: (_, __) => const _StatTile(
                    icon: Icons.campaign_outlined,
                    color: Colors.grey,
                    value: '—',
                    label: 'Updates'),
              ),
            ],
          ),
          const SizedBox(height: 22),

          // --- Upcoming event hero card ------------------------------------
          const _SectionTitle('Upcoming Event'),
          const SizedBox(height: 10),
          eventsAsync.when(
            data: (p) {
              final upcoming = p.data.where(_isUpcoming).toList()
                ..sort((a, b) => a.dateStart.compareTo(b.dateStart));
              if (upcoming.isEmpty) {
                return const _EmptyCard(text: 'No upcoming events right now.');
              }
              return _EventFeatureCard(event: upcoming.first);
            },
            loading: () => const SizedBox(
                height: 180, child: Center(child: CircularProgressIndicator())),
            error: (_, __) => const _EmptyCard(text: "Couldn't load events."),
          ),
          const SizedBox(height: 22),

          // --- Quick actions -------------------------------------------------
          const _SectionTitle('Quick Actions'),
          const SizedBox(height: 10),
          TileGrid(
            columns: 4,
            children: [
              _QuickActionTile(
                icon: Icons.event_outlined,
                color: AppColors.primary,
                label: 'Events',
                builder: (_) => const _TabPage(
                    title: 'Events', child: OfficerEventsScreen()),
              ),
              _QuickActionTile(
                icon: Icons.calendar_month_outlined,
                color: AppColors.purple,
                label: 'Calendar',
                builder: (_) => const _TabPage(
                    title: 'Calendar', child: OfficerCalendarScreen()),
              ),
              _QuickActionTile(
                icon: Icons.receipt_long_outlined,
                color: AppColors.emerald,
                label: 'Fines',
                builder: (_) =>
                    const _TabPage(title: 'Fines', child: OfficerFinesScreen()),
              ),
              _QuickActionTile(
                icon: Icons.campaign_outlined,
                color: AppColors.amber,
                label: 'Announce',
                builder: (_) => const _TabPage(
                    title: 'Announcements',
                    child: OfficerAnnouncementsScreen()),
              ),
            ],
          ),
          if (hasAudit) ...[
            const SizedBox(height: 12),
            const _AuditEntryCard(),
          ],
          const SizedBox(height: 22),

          // --- Latest announcement -----------------------------------------
          Row(
            children: [
              const _SectionTitle('Latest Announcement'),
              const Spacer(),
              TextButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => const _TabPage(
                        title: 'Announcements',
                        child: OfficerAnnouncementsScreen()),
                  ),
                ),
                child: const Text('View all'),
              ),
            ],
          ),
          announcementsAsync.when(
            data: (r) {
              final list = r.$1.data;
              if (list.isEmpty)
                return const _EmptyCard(text: 'No announcements yet.');
              return _AnnouncementCard(announcement: list.first);
            },
            loading: () => const SizedBox(
                height: 100, child: Center(child: CircularProgressIndicator())),
            error: (_, __) =>
                const _EmptyCard(text: "Couldn't load announcements."),
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: .06),
              borderRadius: BorderRadius.circular(AppTheme.radiusLg),
            ),
            child: const Row(
              children: [
                Icon(Icons.qr_code_scanner, size: 18, color: AppColors.primary),
                SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Camera-based attendance scanning is available from a session\'s '
                    '"Scan" button under Events — including offline queueing when '
                    'there\'s no connection.',
                    style:
                        TextStyle(fontSize: 12.5, color: AppColors.textMuted),
                  ),
                ),
              ],
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

/// Dark, bold section heading (replaces the old small blue label).
class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: Theme.of(context)
          .textTheme
          .titleMedium
          ?.copyWith(fontWeight: FontWeight.w800, color: AppColors.text),
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile(
      {required this.icon,
      required this.color,
      required this.value,
      required this.label});
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
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10)),
              child: Icon(icon, size: 18, color: color),
            ),
            const SizedBox(height: 12),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  value,
                  style: const TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.w800,
                      height: 1.1,
                      color: AppColors.text),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(
                  label,
                  style:
                      const TextStyle(fontSize: 12, color: AppColors.textMuted),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
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

    // Events without a cover photo get the navy→blue brand gradient (mockup hero card).
    const brandGradient = BoxDecoration(
      gradient: LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [AppColors.navy, AppColors.primary],
      ),
    );

    return ClipRRect(
      borderRadius: BorderRadius.circular(AppTheme.radiusLg),
      child: Stack(
        children: [
          SizedBox(
            height: 210,
            width: double.infinity,
            child: event.imageUrl != null
                ? Image.network(
                    event.imageUrl!,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) =>
                        const DecoratedBox(decoration: brandGradient),
                  )
                : const DecoratedBox(decoration: brandGradient),
          ),
          if (event.imageUrl != null)
            Positioned.fill(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.bottomCenter,
                    end: Alignment.topCenter,
                    colors: [
                      AppColors.navy.withValues(alpha: 0.92),
                      AppColors.navy.withValues(alpha: 0.15)
                    ],
                  ),
                ),
              ),
            ),
          Positioned(
            top: 14,
            left: 14,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .18),
                  borderRadius: BorderRadius.circular(999)),
              child: const Text('UPCOMING',
                  style: TextStyle(
                      color: Colors.white,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                      letterSpacing: .6)),
            ),
          ),
          Positioned(
            left: 16,
            right: 16,
            bottom: 16,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  event.title,
                  style: const TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 6),
                Row(children: [
                  const Icon(Icons.calendar_today,
                      size: 13, color: Colors.white70),
                  const SizedBox(width: 6),
                  Text(dateLabel,
                      style: const TextStyle(
                          color: Colors.white70, fontSize: 12.5)),
                ]),
                if (event.venue != null && event.venue!.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Row(children: [
                    const Icon(Icons.place_outlined,
                        size: 13, color: Colors.white70),
                    const SizedBox(width: 6),
                    Text(event.venue!,
                        style: const TextStyle(
                            color: Colors.white70, fontSize: 12.5)),
                  ]),
                ],
                const SizedBox(height: 12),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: AppColors.primary),
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute(
                        builder: (_) => const _TabPage(
                            title: 'Events', child: OfficerEventsScreen())),
                  ),
                  child: const Text('View Event Details'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  static bool _sameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;
  static String _fmt(DateTime d) =>
      '${_month[d.month - 1]} ${d.day}, ${d.year}';
  static const _month = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec'
  ];
}

class _AnnouncementCard extends StatelessWidget {
  const _AnnouncementCard({required this.announcement});

  final SomsAnnouncement announcement;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                  color: AppColors.amber.withValues(alpha: .12),
                  borderRadius: BorderRadius.circular(10)),
              child: const Icon(Icons.campaign_outlined,
                  size: 18, color: AppColors.amber),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    announcement.title,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700, color: AppColors.text),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    announcement.body,
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        fontSize: 13, color: AppColors.textMuted),
                  ),
                ],
              ),
            ),
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
        color: Colors.white,
        border: Border.all(color: AppColors.borderStrong),
        borderRadius: BorderRadius.circular(AppTheme.radiusLg),
      ),
      child: Text(text,
          style: const TextStyle(color: AppColors.textMuted, fontSize: 12.5)),
    );
  }
}

/// Icon-over-label shortcut, like the "Quick Actions" row in the mockup.
class _QuickActionTile extends StatelessWidget {
  const _QuickActionTile(
      {required this.icon,
      required this.color,
      required this.label,
      required this.builder});

  final IconData icon;
  final Color color;
  final String label;
  final WidgetBuilder builder;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.zero,
      child: InkWell(
        borderRadius: BorderRadius.circular(AppTheme.radiusLg),
        onTap: () =>
            Navigator.of(context).push(MaterialPageRoute(builder: builder)),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 10),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12)),
                child: Icon(icon, size: 21, color: color),
              ),
              const SizedBox(height: 8),
              Text(
                label,
                style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 11.5,
                    color: AppColors.text),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
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
/// Entry to the Audit workspace, shown only to officers who hold an audit
/// permission (the Auditor). The server checks the permission again.
class _AuditEntryCard extends StatelessWidget {
  const _AuditEntryCard();

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.zero,
      child: InkWell(
        borderRadius: BorderRadius.circular(AppTheme.radiusLg),
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const AuditScreen()),
        ),
        child: const Padding(
          padding: EdgeInsets.all(14),
          child: Row(
            children: [
              Icon(Icons.fact_check_outlined, color: AppColors.primary),
              SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Audit', style: TextStyle(fontWeight: FontWeight.w700)),
                    Text(
                      'Review fines, payments and discrepancies',
                      style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right, color: AppColors.textFaint),
            ],
          ),
        ),
      ),
    );
  }
}

class _TabPage extends StatelessWidget {
  const _TabPage({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Scaffold(appBar: AppBar(title: Text(title)), body: child);
  }
}
