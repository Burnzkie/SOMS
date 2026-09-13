// lib/screens/admin/admin_dashboard_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../providers/admin_providers.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/status_views.dart';
import 'logs_screen.dart';
import 'officer_appointment_screen.dart';
import 'reports_screen.dart';
import 'users_screen.dart';

/// Admin landing tab — there's no GET /api/v1/admin/dashboard endpoint
/// (the web Admin\DashboardController's stats aren't exposed to mobile),
/// so this composes the same figures from the list endpoints the
/// Users/Officers/Logs tabs already call, same approach as the officer
/// dashboard tab.
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
    final approvedAsync = ref.watch(adminUsersProvider((status: 'approved', search: null)));
    final pendingAsync = ref.watch(adminUsersProvider((status: 'pending', search: null)));
    final officersAsync = ref.watch(officerAppointmentPanelProvider);
    final healthAsync = ref.watch(systemHealthProvider);

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(adminUsersProvider);
        ref.invalidate(officerAppointmentPanelProvider);
        ref.invalidate(systemHealthProvider);
      },
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('Good ${_greeting()}, ${firstName.isEmpty ? 'Admin' : firstName} 👋',
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          Text("Here's the state of your organization today.",
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant)),
          const SizedBox(height: 18),

          healthAsync.when(
            data: (health) => Column(
              children: [
                if (!health.treasurerActive)
                  const _WarnBanner('No active Treasurer — Admin is the fallback for clearing/waiving fines.'),
                if (!health.logChainOk)
                  const _WarnBanner('Activity log chain integrity check failed — review immediately.', danger: true),
                if (health.queueStale)
                  const _WarnBanner('Fine-issuance queue worker hasn\'t reported in over 2 hours.'),
              ],
            ),
            loading: () => const SizedBox.shrink(),
            error: (_, __) => const SizedBox.shrink(),
          ),

          // --- Stat grid ---------------------------------------------------
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: 1.35,
            children: [
              approvedAsync.when(
                data: (p) => _StatTile(
                  icon: Icons.people_outline,
                  color: const Color(0xFF8B7CF6),
                  value: '${p.total}',
                  label: 'Approved Students',
                ),
                loading: () => const _StatTile.loading(icon: Icons.people_outline),
                error: (_, __) => const _StatTile(icon: Icons.people_outline, color: Colors.grey, value: '—', label: 'Approved Students'),
              ),
              officersAsync.when(
                data: (r) => _StatTile(
                  icon: Icons.badge_outlined,
                  color: const Color(0xFF1FC98D),
                  value: '${r.$1.where((row) => !row.vacant).length}',
                  label: 'Active Officers',
                ),
                loading: () => const _StatTile.loading(icon: Icons.badge_outlined),
                error: (_, __) => const _StatTile(icon: Icons.badge_outlined, color: Colors.grey, value: '—', label: 'Active Officers'),
              ),
              pendingAsync.when(
                data: (p) => _StatTile(
                  icon: Icons.hourglass_empty,
                  color: const Color(0xFFF5A623),
                  value: '${p.total}',
                  label: 'Pending Approvals',
                ),
                loading: () => const _StatTile.loading(icon: Icons.hourglass_empty),
                error: (_, __) => const _StatTile(icon: Icons.hourglass_empty, color: Colors.grey, value: '—', label: 'Pending Approvals'),
              ),
              healthAsync.when(
                data: (health) => _StatTile(
                  icon: health.logChainOk ? Icons.verified_outlined : Icons.error_outline,
                  color: health.logChainOk ? Theme.of(context).colorScheme.primary : const Color(0xFFF5497A),
                  value: health.logChainOk ? 'OK' : 'Issue',
                  label: 'System Health',
                ),
                loading: () => _StatTile.loading(icon: Icons.verified_outlined),
                error: (_, __) => const _StatTile(icon: Icons.error_outline, color: Colors.grey, value: '—', label: 'System Health'),
              ),
            ],
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
                icon: Icons.people_outline,
                color: const Color(0xFF8B7CF6),
                title: 'Users',
                subtitle: 'Approve & manage users',
                builder: (_) => const _TabPage(title: 'Users', child: AdminUsersScreen()),
              ),
              _QuickAccessCard(
                icon: Icons.badge_outlined,
                color: const Color(0xFF1FC98D),
                title: 'Officers',
                subtitle: 'Appoint & revoke positions',
                builder: (_) => const _TabPage(title: 'Officers', child: OfficerAppointmentScreen()),
              ),
              _QuickAccessCard(
                icon: Icons.picture_as_pdf_outlined,
                color: const Color(0xFFF5A623),
                title: 'Reports',
                subtitle: 'Fine collection reports',
                builder: (_) => const _TabPage(title: 'Reports', child: AdminReportsScreen()),
              ),
              _QuickAccessCard(
                icon: Icons.receipt_long_outlined,
                color: const Color(0xFFF5497A),
                title: 'Logs',
                subtitle: 'Activity log & chain integrity',
                builder: (_) => const _TabPage(title: 'Logs', child: AdminLogsScreen()),
              ),
            ],
          ),
        ],
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

    final color = widget.danger ? const Color(0xFFF5497A) : const Color(0xFFF5A623);
    // Accessibility fix (Sep 2026) — `color` above, used for the actual
    // warning text, measured 1.84-2.89:1 on its own tinted background --
    // under WCAG AA's 4.5:1 (same category of bug fixed elsewhere in this
    // app). Darker same-hue variant for the text only; the icons keep the
    // original, brighter color since they're decorative next to text that
    // already states the warning.
    final textColor = widget.danger ? const Color(0xFFCD0B42) : const Color(0xFF976107);
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(10)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.warning_amber_rounded, size: 16, color: color),
          const SizedBox(width: 8),
          Expanded(child: Text(widget.text, style: TextStyle(fontSize: 12.5, color: textColor))),
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
