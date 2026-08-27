import 'package:flutter/material.dart';

import 'announcements_screen.dart';
import 'calendar_screen.dart';
import 'events_screen.dart';
import 'fines_screen.dart';

/// There's no GET /api/v1/officer/dashboard endpoint (only web has one,
/// Officer\DashboardController) — rather than fabricate a fake summary,
/// this is a simple set of shortcuts into the real feature screens.
/// Quick links push standalone routes rather than switching RoleShell's
/// bottom-nav tab (RoleShell's tab index is private state, not something
/// worth threading a controller through just for this).
class OfficerDashboardScreen extends StatelessWidget {
  const OfficerDashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text('Quick links', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        _LinkCard(
          icon: Icons.event_outlined,
          title: 'Events',
          subtitle: 'Sessions, fine rules, delegate lists',
          builder: (_) => const _TabPage(title: 'Events', child: OfficerEventsScreen()),
        ),
        _LinkCard(
          icon: Icons.calendar_month_outlined,
          title: 'Calendar',
          builder: (_) => const _TabPage(title: 'Calendar', child: OfficerCalendarScreen()),
        ),
        _LinkCard(
          icon: Icons.receipt_long_outlined,
          title: 'Fines',
          subtitle: 'Treasurer + Admin only — you\'ll see a message here otherwise',
          builder: (_) => const _TabPage(title: 'Fines', child: OfficerFinesScreen()),
        ),
        _LinkCard(
          icon: Icons.campaign_outlined,
          title: 'Announcements',
          builder: (_) => const _TabPage(title: 'Announcements', child: OfficerAnnouncementsScreen()),
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
    );
  }
}

class _LinkCard extends StatelessWidget {
  const _LinkCard({required this.icon, required this.title, this.subtitle, required this.builder});

  final IconData icon;
  final String title;
  final String? subtitle;
  final WidgetBuilder builder;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: Icon(icon),
        title: Text(title),
        subtitle: subtitle != null ? Text(subtitle!) : null,
        trailing: const Icon(Icons.chevron_right),
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: builder)),
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
