// lib/screens/auth/register_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../providers/academic_programs_provider.dart';
import '../../providers/auth_provider.dart';

// 1 letter + 10 digits, e.g. P1152302037 — matches RegisterRequest's regex.
// UX convenience only; the API's Form Request remains authoritative.
final _studentIdPattern = RegExp(r'^[A-Za-z]\d{10}$');

const _levels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _studentIdController = TextEditingController();
  final _emailController = TextEditingController();

  String? _department;
  String? _program;
  String? _level;

  bool _submitting = false;
  String? _errorText;

  @override
  void dispose() {
    _nameController.dispose();
    _studentIdController.dispose();
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    // Dropdowns aren't part of Form validation state the same way text
    // fields are — check them explicitly.
    if (_department == null || _program == null || _level == null) {
      setState(() => _errorText = 'Please fill in every field.');
      return;
    }

    setState(() {
      _submitting = true;
      _errorText = null;
    });

    try {
      await ref.read(authProvider.notifier).register(
            name: _nameController.text.trim(),
            studentId: _studentIdController.text.trim(),
            email: _emailController.text.trim(),
            department: _department!,
            program: _program!,
            level: _level!,
          );

      if (!mounted) return;
      // Registration issues no token (pending Admin approval) — surface
      // that clearly, then hand control back to the login screen rather
      // than pretending this was a sign-in.
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
              'Registration submitted. Your account is pending Admin approval.'),
          duration: Duration(seconds: 4),
        ),
      );
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      setState(() => _errorText = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final programsAsync = ref.watch(academicProgramsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Create account')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 16),
          child: Form(
            key: _formKey,
            autovalidateMode: AutovalidateMode.onUserInteraction,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'Register as a student',
                  style: Theme.of(context)
                      .textTheme
                      .headlineSmall
                      ?.copyWith(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 6),
                Text(
                  'Your account needs Admin approval before you can sign in.',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                const SizedBox(height: 24),
                TextFormField(
                  controller: _nameController,
                  decoration: const InputDecoration(labelText: 'Full name'),
                  textInputAction: TextInputAction.next,
                  validator: (v) => (v == null || v.trim().isEmpty)
                      ? 'Name is required.'
                      : null,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _studentIdController,
                  decoration: const InputDecoration(
                    labelText: 'Student ID',
                    hintText: 'P1152302037',
                    counterText: '',
                  ),
                  textInputAction: TextInputAction.next,
                  autocorrect: false,
                  textCapitalization: TextCapitalization.characters,
                  maxLength: 11,
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z0-9]'))
                  ],
                  validator: (v) {
                    if (v == null || v.trim().isEmpty)
                      return 'Student ID is required.';
                    if (!_studentIdPattern.hasMatch(v.trim())) {
                      return '1 letter followed by 10 digits, e.g. P1152302037';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _emailController,
                  decoration: const InputDecoration(labelText: 'Email'),
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.next,
                  autocorrect: false,
                  validator: (v) {
                    if (v == null || v.trim().isEmpty)
                      return 'Email is required.';
                    if (!v.contains('@') || !v.contains('.'))
                      return 'Enter a valid email.';
                    return null;
                  },
                ),
                const SizedBox(height: 16),
                programsAsync.when(
                  loading: () => const Padding(
                    padding: EdgeInsets.symmetric(vertical: 12),
                    child: Center(child: CircularProgressIndicator()),
                  ),
                  error: (err, _) => Text(
                    'Could not load departments. Check your connection and reopen this screen.',
                    style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                        fontSize: 13),
                  ),
                  data: (programsByDepartment) {
                    final departments = programsByDepartment.keys.toList();
                    final programs = _department == null
                        ? <String>[]
                        : programsByDepartment[_department]!;

                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        DropdownButtonFormField<String>(
                          initialValue: _department,
                          decoration:
                              const InputDecoration(labelText: 'Department'),
                          items: [
                            for (final d in departments)
                              DropdownMenuItem(value: d, child: Text(d)),
                          ],
                          onChanged: (v) => setState(() {
                            _department = v;
                            // Previous program almost certainly doesn't belong
                            // to the newly chosen department — the backend's
                            // cross-field check would reject it anyway.
                            _program = null;
                          }),
                          validator: (v) =>
                              v == null ? 'Select a department.' : null,
                        ),
                        const SizedBox(height: 16),
                        DropdownButtonFormField<String>(
                          initialValue: _program,
                          decoration:
                              const InputDecoration(labelText: 'Program'),
                          items: [
                            for (final p in programs)
                              DropdownMenuItem(value: p, child: Text(p)),
                          ],
                          onChanged: _department == null
                              ? null
                              : (v) => setState(() => _program = v),
                          validator: (v) =>
                              v == null ? 'Select a program.' : null,
                        ),
                      ],
                    );
                  },
                ),
                const SizedBox(height: 16),
                DropdownButtonFormField<String>(
                  initialValue: _level,
                  decoration: const InputDecoration(labelText: 'Year level'),
                  items: [
                    for (final l in _levels)
                      DropdownMenuItem(value: l, child: Text(l)),
                  ],
                  onChanged: (v) => setState(() => _level = v),
                  validator: (v) => v == null ? 'Select a year level.' : null,
                ),
                if (_errorText != null) ...[
                  const SizedBox(height: 12),
                  Text(_errorText!,
                      style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                          fontSize: 13)),
                ],
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: _submitting ? null : _submit,
                  style: FilledButton.styleFrom(
                      minimumSize: const Size.fromHeight(48)),
                  child: _submitting
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white))
                      : const Text('Submit registration'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
