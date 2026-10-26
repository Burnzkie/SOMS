// lib/screens/auth/login_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/auth_scaffold.dart';
import 'register_screen.dart';

// 1 letter + 10 digits, e.g. P1152302037 — see 03-Auth-Security.md.
// UX convenience only; the API's Form Requests remain authoritative.
final _studentIdPattern = RegExp(r'^[A-Za-z]\d{10}$');

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _studentIdController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;
  bool _submitting = false;
  String? _errorText;

  @override
  void dispose() {
    _studentIdController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _submitting = true;
      _errorText = null;
    });

    try {
      await ref.read(authProvider.notifier).login(
            _studentIdController.text.trim(),
            _passwordController.text,
          );
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
        // Live feedback as soon as the field has been touched once —
        // this is what surfaces the red error text while typing,
        // instead of only on submit.
        autovalidateMode: AutovalidateMode.onUserInteraction,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            const AuthHeader(
              title: 'Welcome back',
              subtitle: 'Sign in with your Student ID to access SOMS.',
            ),
            TextFormField(
              controller: _studentIdController,
              decoration: const InputDecoration(
                labelText: 'Student ID',
                hintText: 'P1152302037',
                counterText:
                    '', // hides the default "0/11" counter — maxLength still enforced
              ),
              textInputAction: TextInputAction.next,
              autocorrect: false,
              textCapitalization: TextCapitalization.characters,
              maxLength:
                  11, // hard cap — nothing can be typed past 11 characters
              inputFormatters: [
                // Only letters and digits can be typed at all — blocks
                // spaces, symbols, everything outside the format up front,
                // not just after the fact via the validator.
                FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z0-9]')),
              ],
              validator: (v) {
                if (v == null || v.trim().isEmpty) {
                  return 'Student ID is required.';
                }
                if (!_studentIdPattern.hasMatch(v.trim())) {
                  return '1 letter followed by 10 digits, e.g. P1152302037';
                }
                return null;
              },
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _passwordController,
              decoration: InputDecoration(
                labelText: 'Password',
                suffixIcon: IconButton(
                  icon: Icon(_obscurePassword
                      ? Icons.visibility_off
                      : Icons.visibility),
                  onPressed: () =>
                      setState(() => _obscurePassword = !_obscurePassword),
                ),
              ),
              obscureText: _obscurePassword,
              textInputAction: TextInputAction.done,
              onFieldSubmitted: (_) => _submit(),
              validator: (v) =>
                  (v == null || v.isEmpty) ? 'Password is required.' : null,
            ),
            if (_errorText != null) ...[
              const SizedBox(height: 14),
              AuthErrorBanner(message: _errorText!),
            ],
            const SizedBox(height: 24),
            GradientButton(
              label: 'Sign in',
              loading: _submitting,
              onPressed: _submitting ? null : _submit,
            ),
            const SizedBox(height: 14),
            Center(
              child: TextButton(
                onPressed: _submitting
                    ? null
                    : () => Navigator.of(context).push(
                          MaterialPageRoute(
                              builder: (_) => const RegisterScreen()),
                        ),
                child: const Text('New student? Create an account'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
