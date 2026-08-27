import 'package:flutter/material.dart';

import '../../widgets/role_shell.dart';
import 'announcements_screen.dart';
import 'events_screen.dart';
import 'fines_screen.dart';
import 'qr_screen.dart';
import 'student_dashboard_screen.dart';

class StudentHomeScreen extends StatelessWidget {
  const StudentHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const RoleShell(
      title: 'SOMS',
      items: [
        RoleNavItem(label: 'Home', icon: Icons.home_outlined, builder: _home),
        RoleNavItem(label: 'My QR', icon: Icons.qr_code_2, builder: _qr),
        RoleNavItem(label: 'Events', icon: Icons.event_outlined, builder: _events),
        RoleNavItem(label: 'Fines', icon: Icons.receipt_long_outlined, builder: _fines),
        RoleNavItem(label: 'Announce', icon: Icons.campaign_outlined, builder: _announcements),
      ],
    );
  }

  static Widget _home(BuildContext ctx) => const StudentDashboardScreen();
  static Widget _qr(BuildContext ctx) => const QrScreen();
  static Widget _events(BuildContext ctx) => const StudentEventsScreen();
  static Widget _fines(BuildContext ctx) => const StudentFinesScreen();
  static Widget _announcements(BuildContext ctx) => const StudentAnnouncementsScreen();
}
