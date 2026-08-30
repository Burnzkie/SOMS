// lib/providers/profile_providers.dart

import 'dart:io';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/user_profile.dart';
import '../services/profile_service.dart';
// Reuses the app's real apiClientProvider (defined in auth_provider.dart)
// instead of a separate Dio instance — same token handling, same
// ApiException shape, same 401 interceptor as the rest of the app.
import 'auth_provider.dart';

final profileServiceProvider = Provider<ProfileService>((ref) {
  final api = ref.watch(apiClientProvider);
  return ProfileService(api);
});

/// Holds the current user's profile. AsyncNotifier so screens get
/// loading/error/data states for free via .when() in the UI, and so
/// updateProfile() can push a new value in without a full refetch.
class ProfileNotifier extends AsyncNotifier<UserProfile> {
  @override
  Future<UserProfile> build() {
    return ref.read(profileServiceProvider).fetchProfile();
  }

  Future<void> updateProfile(Map<String, String> changedFields) async {
    final service = ref.read(profileServiceProvider);
    state = const AsyncLoading();
    state = await AsyncValue.guard(() => service.updateProfile(changedFields));
  }
}

final profileNotifierProvider =
    AsyncNotifierProvider<ProfileNotifier, UserProfile>(ProfileNotifier.new);

/// Separate from profileNotifierProvider since avatar has its own
/// create/replace/delete lifecycle and shouldn't force a full profile
/// refetch on every photo change.
class AvatarNotifier extends AsyncNotifier<String?> {
  @override
  Future<String?> build() {
    return ref.read(profileServiceProvider).fetchAvatarUrl();
  }

  Future<void> upload(String filePath) async {
    final service = ref.read(profileServiceProvider);
    final hasExisting = state.value != null;
    state = const AsyncLoading();
    state = await AsyncValue.guard(
      () => service.uploadAvatar(File(filePath), hasExistingAvatar: hasExisting),
    );
  }

  Future<void> remove() async {
    final service = ref.read(profileServiceProvider);
    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      await service.deleteAvatar();
      return null;
    });
  }
}

final avatarNotifierProvider =
    AsyncNotifierProvider<AvatarNotifier, String?>(AvatarNotifier.new);
