// lib/services/profile_service.dart

import 'dart:io';
import 'package:dio/dio.dart' show FormData, MultipartFile;
import '../core/api_client.dart';
import '../models/user_profile.dart';

/// Uses the app's own ApiClient (core/api_client.dart) — same token
/// handling, same ApiException error shape, same 401 -> forceLogout
/// interceptor as every other feature. No separate Dio instance.
class ProfileService {
  final ApiClient _api;
  ProfileService(this._api);

  /// GET /api/v1/profile
  Future<UserProfile> fetchProfile() async {
    final res = await _api.get('/profile');
    return UserProfile.fromJson(res['data'] as Map<String, dynamic>);
  }

  /// PUT /api/v1/profile
  /// Only include fields that changed — the backend uses `sometimes`
  /// validation, so omitted keys are left untouched server-side.
  Future<UserProfile> updateProfile(Map<String, String> changedFields) async {
    final res = await _api.put('/profile', data: changedFields);
    return UserProfile.fromJson(res['data'] as Map<String, dynamic>);
  }

  /// GET /api/v1/avatar
  Future<String?> fetchAvatarUrl() async {
    final res = await _api.get('/avatar');
    final data = res['data'] as Map<String, dynamic>;
    return data['has_avatar'] == true ? data['avatar_url'] as String : null;
  }

  /// Uploads a new avatar. Picks POST (create) vs PUT (replace)
  /// automatically based on whether the user already has one, matching
  /// the backend's create-vs-replace split.
  Future<String> uploadAvatar(File imageFile, {required bool hasExistingAvatar}) async {
    final formData = FormData.fromMap({
      'avatar': await MultipartFile.fromFile(imageFile.path),
    });

    final res = await _api.postMultipart(
      '/avatar',
      formData,
      method: hasExistingAvatar ? 'PUT' : 'POST',
    );

    final data = res['data'] as Map<String, dynamic>;
    return data['avatar_url'] as String;
  }

  /// DELETE /api/v1/avatar
  Future<void> deleteAvatar() async {
    await _api.delete('/avatar');
  }
}
