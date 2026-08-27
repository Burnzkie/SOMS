import 'package:flutter/material.dart';

import '../../widgets/role_shell.dart';
import 'logs_screen.dart';
import 'officer_appointment_screen.dart';
import 'reports_screen.dart';
import 'users_screen.dart';

class AdminHomeScreen extends StatelessWidget {
  const AdminHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const RoleShell(
      title: 'SOMS — Admin',
      items: [
        RoleNavItem(label: 'Users', icon: Icons.people_outline, builder: _users),
        RoleNavItem(label: 'Officers', icon: Icons.badge_outlined, builder: _officers),
        RoleNavItem(label: 'Reports', icon: Icons.picture_as_pdf_outlined, builder: _reports),
        RoleNavItem(label: 'Logs', icon: Icons.receipt_long_outlined, builder: _logs),
      ],
    );
  }

  static Widget _users(BuildContext ctx) => const AdminUsersScreen();
  static Widget _officers(BuildContext ctx) => const OfficerAppointmentScreen();
  static Widget _reports(BuildContext ctx) => const AdminReportsScreen();
  static Widget _logs(BuildContext ctx) => const AdminLogsScreen();
}
