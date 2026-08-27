import 'package:flutter/material.dart';

import '../../widgets/role_shell.dart';
import 'announcements_screen.dart';
import 'calendar_screen.dart';
import 'events_screen.dart';
import 'fines_screen.dart';
import 'officer_dashboard_screen.dart';

class OfficerHomeScreen extends StatelessWidget {
  const OfficerHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const RoleShell(
      title: 'SOMS — Officer',
      items: [
        RoleNavItem(label: 'Dashboard', icon: Icons.dashboard_outlined, builder: _dashboard),
        RoleNavItem(label: 'Events', icon: Icons.event_outlined, builder: _events),
        RoleNavItem(label: 'Calendar', icon: Icons.calendar_month_outlined, builder: _calendar),
        // Treasurer+Admin only server-side (FinePolicy) -- shown to every
        // officer since the login response doesn't carry position/tier,
        // and the screen itself handles the 403 gracefully either way.
        RoleNavItem(label: 'Fines', icon: Icons.receipt_long_outlined, builder: _fines),
        RoleNavItem(label: 'Announce', icon: Icons.campaign_outlined, builder: _announcements),
      ],
    );
  }

  static Widget _dashboard(BuildContext ctx) => const OfficerDashboardScreen();
  static Widget _events(BuildContext ctx) => const OfficerEventsScreen();
  static Widget _calendar(BuildContext ctx) => const OfficerCalendarScreen();
  static Widget _fines(BuildContext ctx) => const OfficerFinesScreen();
  static Widget _announcements(BuildContext ctx) => const OfficerAnnouncementsScreen();
}
