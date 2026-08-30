// lib/screens/settings/edit_profile_screen.dart

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../../models/user_profile.dart';
import '../../providers/profile_providers.dart';
import '../../core/api_client.dart';

class EditProfileScreen extends ConsumerStatefulWidget {
  const EditProfileScreen({super.key});

  @override
  ConsumerState<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends ConsumerState<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _nameController;
  late final TextEditingController _emailController;
  late final TextEditingController _departmentController;
  late final TextEditingController _programController;
  late final TextEditingController _levelController;

  bool _controllersInitialized = false;
  bool _isSaving = false;
  String? _errorMessage;

  /// Initializes text controllers once, from the first successfully
  /// loaded profile — avoids stomping user edits if the provider ever
  /// rebuilds mid-edit.
  void _initControllersIfNeeded(UserProfile profile) {
    if (_controllersInitialized) return;
    _nameController = TextEditingController(text: profile.name);
    _emailController = TextEditingController(text: profile.email);
    _departmentController =
        TextEditingController(text: profile.department ?? '');
    _programController = TextEditingController(text: profile.program ?? '');
    _levelController = TextEditingController(text: profile.level ?? '');
    _controllersInitialized = true;
  }

  @override
  void dispose() {
    if (_controllersInitialized) {
      _nameController.dispose();
      _emailController.dispose();
      _departmentController.dispose();
      _programController.dispose();
      _levelController.dispose();
    }
    super.dispose();
  }

  Future<void> _pickAndUploadAvatar() async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 85, // matches backend's 2MB cap headroom
    );
    if (picked == null) return;

    try {
      await ref.read(avatarNotifierProvider.notifier).upload(picked.path);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _save(UserProfile original) async {
    if (!_formKey.currentState!.validate()) return;

    // Only send fields that actually changed — keeps the PUT payload
    // minimal and avoids tripping the unique-email check on an unchanged
    // email.
    final changed = <String, String>{};
    if (_nameController.text != original.name)
      changed['name'] = _nameController.text;
    if (_emailController.text != original.email)
      changed['email'] = _emailController.text;
    if (_departmentController.text != (original.department ?? '')) {
      changed['department'] = _departmentController.text;
    }
    if (_programController.text != (original.program ?? '')) {
      changed['program'] = _programController.text;
    }
    if (_levelController.text != (original.level ?? '')) {
      changed['level'] = _levelController.text;
    }

    if (changed.isEmpty) {
      Navigator.of(context).pop();
      return;
    }

    setState(() {
      _isSaving = true;
      _errorMessage = null;
    });

    try {
      await ref.read(profileNotifierProvider.notifier).updateProfile(changed);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Profile updated.')),
      );
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      setState(() => _errorMessage = e.message);
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final profileAsync = ref.watch(profileNotifierProvider);
    final avatarAsync = ref.watch(avatarNotifierProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Edit Profile')),
      body: profileAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) =>
            Center(child: Text('Failed to load profile: $error')),
        data: (profile) {
          _initControllersIfNeeded(profile);

          return Padding(
            padding: const EdgeInsets.all(16),
            child: Form(
              key: _formKey,
              child: ListView(
                children: [
                  Center(
                    child: GestureDetector(
                      onTap:
                          avatarAsync.isLoading ? null : _pickAndUploadAvatar,
                      child: Stack(
                        children: [
                          CircleAvatar(
                            radius: 48,
                            backgroundImage: avatarAsync.value != null
                                ? NetworkImage(avatarAsync.value!)
                                : null,
                            child: avatarAsync.isLoading
                                ? const CircularProgressIndicator()
                                : (avatarAsync.value == null
                                    ? const Icon(Icons.person, size: 48)
                                    : null),
                          ),
                          const Positioned(
                            bottom: 0,
                            right: 0,
                            child: CircleAvatar(
                              radius: 14,
                              child: Icon(Icons.camera_alt, size: 16),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),
                  if (_errorMessage != null) ...[
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.errorContainer,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        _errorMessage!,
                        style: TextStyle(
                            color:
                                Theme.of(context).colorScheme.onErrorContainer),
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],
                  TextFormField(
                    controller: _nameController,
                    decoration: const InputDecoration(labelText: 'Full name'),
                    validator: (value) =>
                        (value == null || value.trim().isEmpty)
                            ? 'Name is required'
                            : null,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _emailController,
                    keyboardType: TextInputType.emailAddress,
                    decoration: const InputDecoration(labelText: 'Email'),
                    validator: (value) {
                      if (value == null || value.trim().isEmpty)
                        return 'Email is required';
                      if (!value.contains('@')) return 'Enter a valid email';
                      return null;
                    },
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _departmentController,
                    decoration: const InputDecoration(labelText: 'Department'),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _programController,
                    decoration: const InputDecoration(labelText: 'Program'),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _levelController,
                    decoration: const InputDecoration(labelText: 'Year level'),
                  ),
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: _isSaving ? null : () => _save(profile),
                    child: _isSaving
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Save Changes'),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
