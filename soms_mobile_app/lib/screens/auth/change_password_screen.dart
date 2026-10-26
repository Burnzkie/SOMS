// lib/screens/auth/change_password_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/auth_scaffold.dart';

class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  ConsumerState<ChangePasswordScreen> createState() =>
      _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends ConsumerState<ChangePasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _currentController = TextEditingController();
  final _newController = TextEditingController();
  final _confirmController = TextEditingController();
  bool _submitting = false;
  String? _errorText;

  @override
  void dispose() {
    _currentController.dispose();
    _newController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _submitting = true;
      _errorText = null;
    });

    try {
      await ref.read(authProvider.notifier).changePassword(
            currentPassword: _currentController.text,
            password: _newController.text,
            passwordConfirmation: _confirmController.text,
          );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text('Password changed. Please log in again.')),
        );
        // No-op when this screen is AuthGate's root during forced onboarding
        // (canPop is false there — AuthGate swaps the screen automatically
        // once authProvider's state flips to unauthenticated). When reused
        // from Settings (pushed on top of an authenticated screen), this
        // pops back to reveal the LoginScreen AuthGate already swapped in.
        if (Navigator.of(context).canPop()) {
          Navigator.of(context).pop();
        }
      }
    } on ApiException catch (e) {
      setState(() => _errorText = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AuthScaffold(
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            const AuthHeader(
              title: 'Change your password',
              subtitle:
                  'You\'re using a default or expired password. Set a new one to continue — '
                  'this is required before you can access your dashboard.',
            ),
            TextFormField(
              controller: _currentController,
              decoration: const InputDecoration(labelText: 'Current password'),
              obscureText: true,
              validator: (v) => (v == null || v.isEmpty) ? 'Required.' : null,
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _newController,
              decoration: const InputDecoration(labelText: 'New password'),
              obscureText: true,
              validator: (v) {
                if (v == null || v.length < 8) return 'At least 8 characters.';
                if (!RegExp(r'[a-z]').hasMatch(v) ||
                    !RegExp(r'[A-Z]').hasMatch(v)) {
                  return 'Must include upper and lower case letters.';
                }
                if (!RegExp(r'\d').hasMatch(v))
                  return 'Must include at least one number.';
                return null;
              },
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _confirmController,
              decoration:
                  const InputDecoration(labelText: 'Confirm new password'),
              obscureText: true,
              validator: (v) =>
                  v != _newController.text ? 'Passwords don\'t match.' : null,
            ),
            if (_errorText != null) ...[
              const SizedBox(height: 14),
              AuthErrorBanner(message: _errorText!),
            ],
            const SizedBox(height: 24),
            GradientButton(
              label: 'Update password',
              loading: _submitting,
              onPressed: _submitting ? null : _submit,
            ),
          ],
        ),
      ),
    );
  }
}
