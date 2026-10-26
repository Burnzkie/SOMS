// lib/screens/student/student_dashboard_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/student_providers.dart';
import '../../widgets/status_views.dart';
import '../../widgets/tile_grid.dart';
import 'announcements_screen.dart';
import 'events_screen.dart';
import 'fines_screen.dart';
import 'qr_screen.dart';

/// Student landing tab — same stat/hero/quick-actions layout as the officer
/// and admin dashboards, but backed by the real GET /api/v1/student/dashboard
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
        error: (e, _) => ErrorRetryView(
            message: '$e',
            onRetry: () => ref.invalidate(studentDashboardProvider)),
        data: (data) {
          final unpaidCount = data['unpaid_fines_count'] as int? ?? 0;
          final unpaidAmount =
              double.tryParse('${data['unpaid_fines_amount'] ?? 0}') ?? 0;
          final events = (data['upcoming_events'] as List<dynamic>? ?? [])
              .cast<Map<String, dynamic>>();
          final announcements =
              (data['recent_announcements'] as List<dynamic>? ?? [])
                  .cast<Map<String, dynamic>>();

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(
                'Good ${_greeting()}, ${firstName.isEmpty ? 'there' : firstName}!',
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

              if (unpaidCount > 0) ...[
                _UnpaidFinesBanner(count: unpaidCount, amount: unpaidAmount),
                const SizedBox(height: 16),
              ],

              // --- Stat row ---------------------------------------------
              Row(
                children: [
                  Expanded(
                    child: _StatTile(
                      icon: Icons.event_outlined,
                      color: AppColors.primary,
                      value: '${events.length}',
                      label: 'Events',
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _StatTile(
                      icon: Icons.payments_outlined,
                      color: AppColors.emerald,
                      value: '$unpaidCount',
                      label: 'Unpaid Fines',
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _StatTile(
                      icon: Icons.campaign_outlined,
                      color: AppColors.amber,
                      value: '${announcements.length}',
                      label: 'Updates',
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 22),

              // --- Upcoming event hero card ------------------------------
              const _SectionTitle('Upcoming Event'),
              const SizedBox(height: 10),
              events.isEmpty
                  ? const _EmptyCard(text: 'Nothing scheduled right now.')
                  : _EventFeatureCard(event: events.first),
              const SizedBox(height: 22),

              // --- Quick actions (icon-over-label row, like the mockup) --
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
                        title: 'Events', child: StudentEventsScreen()),
                  ),
                  _QuickActionTile(
                    icon: Icons.qr_code_2,
                    color: AppColors.purple,
                    label: 'My QR',
                    builder: (_) =>
                        const _TabPage(title: 'My QR', child: QrScreen()),
                  ),
                  _QuickActionTile(
                    icon: Icons.payments_outlined,
                    color: AppColors.emerald,
                    label: 'Fines',
                    builder: (_) => const _TabPage(
                        title: 'Fines', child: StudentFinesScreen()),
                  ),
                  _QuickActionTile(
                    icon: Icons.campaign_outlined,
                    color: AppColors.amber,
                    label: 'Announce',
                    builder: (_) => const _TabPage(
                        title: 'Announcements',
                        child: StudentAnnouncementsScreen()),
                  ),
                ],
              ),
              const SizedBox(height: 22),

              // --- Latest announcement ------------------------------------
              Row(
                children: [
                  const _SectionTitle('Latest Announcement'),
                  const Spacer(),
                  TextButton(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const _TabPage(
                            title: 'Announcements',
                            child: StudentAnnouncementsScreen()),
                      ),
                    ),
                    child: const Text('View all'),
                  ),
                ],
              ),
              announcements.isEmpty
                  ? const _EmptyCard(text: 'No announcements yet.')
                  : _AnnouncementCard(announcement: announcements.first),
            ],
          );
        },
      ),
    );
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

/// Unpaid-fines notice, styled like the web's .alert-error box.
class _UnpaidFinesBanner extends StatelessWidget {
  const _UnpaidFinesBanner({required this.count, required this.amount});

  final int count;
  final double amount;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.rose.withValues(alpha: .10),
        borderRadius: BorderRadius.circular(AppTheme.radiusLg),
        border: Border.all(color: AppColors.rose.withValues(alpha: .30)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.receipt_long, size: 20, color: AppColors.roseText),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '$count unpaid fine${count == 1 ? '' : 's'} — ₱${amount.toStringAsFixed(2)}',
                  style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                      color: AppColors.roseText),
                ),
                const SizedBox(height: 2),
                const Text(
                  "Pay in person at the Treasurer's office.",
                  style: TextStyle(fontSize: 12.5, color: AppColors.roseText),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile(
      {required this.icon,
      required this.color,
      required this.value,
      required this.label});

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
            Text(
              value,
              style: const TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                  height: 1.1,
                  color: AppColors.text),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
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
            child: imageUrl != null
                ? Image.network(
                    imageUrl,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) =>
                        const DecoratedBox(decoration: brandGradient),
                  )
                : const DecoratedBox(decoration: brandGradient),
          ),
          if (imageUrl != null)
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
              child: const Text(
                'UPCOMING',
                style: TextStyle(
                    color: Colors.white,
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    letterSpacing: .6),
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
                Text(title,
                    style: const TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w800)),
                const SizedBox(height: 6),
                Row(children: [
                  const Icon(Icons.calendar_today,
                      size: 13, color: Colors.white70),
                  const SizedBox(width: 6),
                  Text(
                    _fmtRange(event['date_start'], event['date_end']),
                    style:
                        const TextStyle(color: Colors.white70, fontSize: 12.5),
                  ),
                ]),
                if (venue != null && venue.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Row(children: [
                    const Icon(Icons.place_outlined,
                        size: 13, color: Colors.white70),
                    const SizedBox(width: 6),
                    Text(venue,
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
                            title: 'Events', child: StudentEventsScreen())),
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
}

class _AnnouncementCard extends StatelessWidget {
  const _AnnouncementCard({required this.announcement});

  final Map<String, dynamic> announcement;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
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
              child: Text(
                announcement['title'] as String? ?? '',
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700, color: AppColors.text),
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
      height: 90,
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
class _TabPage extends StatelessWidget {
  const _TabPage({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Scaffold(appBar: AppBar(title: Text(title)), body: child);
  }
}
