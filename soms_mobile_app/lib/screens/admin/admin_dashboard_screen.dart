// lib/screens/admin/admin_dashboard_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/app_theme.dart';
import '../../providers/admin_providers.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/tile_grid.dart';
import 'admin_findings_screen.dart';
import 'logs_screen.dart';
import 'officer_appointment_screen.dart';
import 'reports_screen.dart';
import 'users_screen.dart';

/// Admin landing tab — there's no GET /api/v1/admin/dashboard endpoint
/// (the web Admin\DashboardController's stats aren't exposed to mobile),
/// so this composes the same figures from the list endpoints the
/// Users/Officers/Logs tabs already call, same approach as the officer
/// dashboard tab. Styled to match the web admin dashboard and the SOMS
/// mobile mockup (same tiles/sections as the officer dashboard).
class AdminDashboardScreen extends ConsumerWidget {
  const AdminDashboardScreen({super.key});

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
    final approvedAsync =
        ref.watch(adminUsersProvider((status: 'approved', search: null)));
    final pendingAsync =
        ref.watch(adminUsersProvider((status: 'pending', search: null)));
    final officersAsync = ref.watch(officerAppointmentPanelProvider);
    final healthAsync = ref.watch(systemHealthProvider);

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(adminUsersProvider);
        ref.invalidate(officerAppointmentPanelProvider);
        ref.invalidate(systemHealthProvider);
        ref.invalidate(adminFindingsProvider);
      },
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Good ${_greeting()}, ${firstName.isEmpty ? 'Admin' : firstName}!',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  letterSpacing: -.3,
                  color: AppColors.text,
                ),
          ),
          const SizedBox(height: 4),
          const Text(
            "Here's the state of your organization today.",
            style: TextStyle(fontSize: 13, color: AppColors.textMuted),
          ),
          const SizedBox(height: 18),

          healthAsync.when(
            data: (health) => Column(
              children: [
                if (!health.treasurerActive)
                  const _WarnBanner(
                      'No active Treasurer — Admin is the fallback for clearing/waiving fines.'),
                if (!health.logChainOk)
                  const _WarnBanner(
                      'Activity log chain integrity check failed — review immediately.',
                      danger: true),
                if (health.queueStale)
                  const _WarnBanner(
                      'Fine-issuance queue worker hasn\'t reported in over 2 hours.'),
              ],
            ),
            loading: () => const SizedBox.shrink(),
            error: (_, __) => const SizedBox.shrink(),
          ),

          // --- Stat grid (2x2, same four tiles as the web dashboard) -------
          TileGrid(
            columns: 2,
            children: [
              approvedAsync.when(
                data: (p) => _StatTile(
                  icon: Icons.people_outline,
                  color: AppColors.purple,
                  value: '${p.total}',
                  label: 'Approved Students',
                ),
                loading: () =>
                    const _StatTile.loading(icon: Icons.people_outline),
                error: (_, __) => const _StatTile(
                    icon: Icons.people_outline,
                    color: Colors.grey,
                    value: '—',
                    label: 'Approved Students'),
              ),
              officersAsync.when(
                data: (r) => _StatTile(
                  icon: Icons.badge_outlined,
                  color: AppColors.emerald,
                  value: '${r.$1.where((row) => !row.vacant).length}',
                  label: 'Active Officers',
                ),
                loading: () =>
                    const _StatTile.loading(icon: Icons.badge_outlined),
                error: (_, __) => const _StatTile(
                    icon: Icons.badge_outlined,
                    color: Colors.grey,
                    value: '—',
                    label: 'Active Officers'),
              ),
              pendingAsync.when(
                data: (p) => _StatTile(
                  icon: Icons.hourglass_empty,
                  color: AppColors.amber,
                  value: '${p.total}',
                  label: 'Pending Approvals',
                ),
                loading: () =>
                    const _StatTile.loading(icon: Icons.hourglass_empty),
                error: (_, __) => const _StatTile(
                    icon: Icons.hourglass_empty,
                    color: Colors.grey,
                    value: '—',
                    label: 'Pending Approvals'),
              ),
              healthAsync.when(
                data: (health) => _StatTile(
                  icon: health.logChainOk
                      ? Icons.verified_outlined
                      : Icons.error_outline,
                  color: health.logChainOk ? AppColors.primary : AppColors.rose,
                  value: health.logChainOk ? 'OK' : 'Issue',
                  label: 'System Health',
                ),
                loading: () =>
                    const _StatTile.loading(icon: Icons.verified_outlined),
                error: (_, __) => const _StatTile(
                    icon: Icons.error_outline,
                    color: Colors.grey,
                    value: '—',
                    label: 'System Health'),
              ),
            ],
          ),
          const SizedBox(height: 22),

          // --- Quick actions (icon-over-label row, like the mockup) --------
          Text(
            'Quick Actions',
            style: Theme.of(context)
                .textTheme
                .titleMedium
                ?.copyWith(fontWeight: FontWeight.w800, color: AppColors.text),
          ),
          const SizedBox(height: 10),
          TileGrid(
            columns: 4,
            children: [
              _QuickActionTile(
                icon: Icons.people_outline,
                color: AppColors.purple,
                label: 'Users',
                builder: (_) =>
                    const _TabPage(title: 'Users', child: AdminUsersScreen()),
              ),
              _QuickActionTile(
                icon: Icons.badge_outlined,
                color: AppColors.emerald,
                label: 'Officers',
                builder: (_) => const _TabPage(
                    title: 'Officers', child: OfficerAppointmentScreen()),
              ),
              _QuickActionTile(
                icon: Icons.picture_as_pdf_outlined,
                color: AppColors.amber,
                label: 'Reports',
                builder: (_) => const _TabPage(
                    title: 'Reports', child: AdminReportsScreen()),
              ),
              _QuickActionTile(
                icon: Icons.receipt_long_outlined,
                color: AppColors.rose,
                label: 'Logs',
                builder: (_) =>
                    const _TabPage(title: 'Logs', child: AdminLogsScreen()),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const _AuditFindingsCard(),
        ],
      ),
    );
  }
}

/// Entry to the Auditor's findings, with how many still need the Admin.
class _AuditFindingsCard extends ConsumerWidget {
  const _AuditFindingsCard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final waiting = ref
        .watch(adminFindingsProvider((status: '', page: 1)))
        .maybeWhen(data: (result) => result.$2, orElse: () => null);

    return Card(
      margin: EdgeInsets.zero,
      child: InkWell(
        borderRadius: BorderRadius.circular(AppTheme.radiusLg),
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const AdminFindingsScreen()),
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              const Icon(Icons.fact_check_outlined, color: AppColors.primary),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Audit findings', style: TextStyle(fontWeight: FontWeight.w700)),
                    Text(
                      waiting == null
                          ? 'Review what the Auditor reported'
                          : waiting == 0
                              ? 'Nothing waiting for action'
                              : '$waiting waiting for action',
                      style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: AppColors.textFaint),
            ],
          ),
        ),
      ),
    );
  }
}

class _WarnBanner extends StatefulWidget {
  const _WarnBanner(this.text, {this.danger = false});

  final String text;
  final bool danger;

  @override
  State<_WarnBanner> createState() => _WarnBannerState();
}

class _WarnBannerState extends State<_WarnBanner> {
  // Fix (Sep 2026) — these had no way to dismiss at all; a resolved issue
  // (e.g. a Treasurer gets appointed) stayed on screen until the
  // underlying health check cleared on its own, with no way for the admin
  // to acknowledge it sooner. Local widget state is enough here — these
  // banners are re-derived from live health data on every rebuild/refresh,
  // so a dismissal isn't meant to persist across app restarts or even a
  // pull-to-refresh; it's "I've seen this", not "never show me this again".
  bool _dismissed = false;

  @override
  Widget build(BuildContext context) {
    if (_dismissed) return const SizedBox.shrink();

    final color = widget.danger ? AppColors.rose : AppColors.amber;
    // Accessibility fix (Sep 2026) — `color` above, used for the actual
    // warning text, measured 1.84-2.89:1 on its own tinted background --
    // under WCAG AA's 4.5:1 (same category of bug fixed elsewhere in this
    // app). Darker same-hue variant for the text only; the icons keep the
    // original, brighter color since they're decorative next to text that
    // already states the warning.
    final textColor =
        widget.danger ? const Color(0xFFCD0B42) : const Color(0xFF976107);
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(AppTheme.radiusMd),
        border: Border.all(color: color.withValues(alpha: 0.30)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.warning_amber_rounded, size: 16, color: color),
          const SizedBox(width: 8),
          Expanded(
              child: Text(widget.text,
                  style: TextStyle(fontSize: 12.5, color: textColor))),
          const SizedBox(width: 4),
          InkWell(
            onTap: () => setState(() => _dismissed = true),
            borderRadius: BorderRadius.circular(12),
            child: Padding(
              padding: const EdgeInsets.all(2),
              child: Icon(Icons.close, size: 16, color: color),
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
