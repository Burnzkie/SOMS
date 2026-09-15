import 'dart:io';

import 'package:dio/dio.dart';
import 'package:open_filex/open_filex.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';

import '../core/api_client.dart';
import '../models/app_version.dart';

/// Result of a version check — deliberately not just "AppVersion?" so the
/// UI can tell "no update" apart from "update available but not forced"
/// apart from "must update now" without re-deriving that from raw fields
/// at every call site.
enum UpdateUrgency { none, optional, forced, unsupported }

class UpdateCheckResult {
  const UpdateCheckResult(this.urgency, this.remote);
  final UpdateUrgency urgency;
  final AppVersion? remote;
}

/// See 10-Mobile-Deployment.md Part B — in-app auto-update.
///
/// Three steps, each independently retriable: check (GET /app-version),
/// download (Dio to app-local storage, with progress), install (hand the
/// downloaded file to the OS package installer via open_filex).
///
/// Uses its own plain Dio for the download rather than ApiClient — the
/// APK lives on R2, not the Laravel API's baseUrl, and ApiClient's
/// _request() unwraps `{success, data}` JSON envelopes, which doesn't
/// apply to a raw binary download.
class UpdateService {
  UpdateService(this._api);

  final ApiClient _api;

  /// GET /api/v1/app-version, compared against this install's own
  /// versionCode (PackageInfo.buildNumber — the `+N` in pubspec.yaml).
  Future<UpdateCheckResult> checkForUpdate() async {
    final res = await _api.get('/app-version');
    final data = res['data'] as Map<String, dynamic>?;

    if (data == null) {
      return const UpdateCheckResult(UpdateUrgency.none, null);
    }

    final remote = AppVersion.fromJson(data);
    final info = await PackageInfo.fromPlatform();
    final currentCode = int.tryParse(info.buildNumber) ?? 0;

    if (remote.minSupportedVersionCode != null &&
        currentCode < remote.minSupportedVersionCode!) {
      return UpdateCheckResult(UpdateUrgency.unsupported, remote);
    }

    if (remote.versionCode <= currentCode) {
      return const UpdateCheckResult(UpdateUrgency.none, null);
    }

    return UpdateCheckResult(
      remote.forceUpdate ? UpdateUrgency.forced : UpdateUrgency.optional,
      remote,
    );
  }

  /// Downloads [version]'s APK to app-local storage (getApplicationSupportDirectory
  /// — cleared on uninstall, not visible to other apps, no storage
  /// permission needed on API 29+). [onProgress] receives 0.0–1.0.
  Future<File> downloadApk(
    AppVersion version, {
    void Function(double progress)? onProgress,
  }) async {
    final dir = await getApplicationSupportDirectory();
    final savePath = '${dir.path}/soms-${version.versionCode}.apk';

    await Dio().download(
      version.apkUrl,
      savePath,
      onReceiveProgress: (received, total) {
        if (total > 0 && onProgress != null) {
          onProgress(received / total);
        }
      },
    );

    return File(savePath);
  }

  /// Hands the downloaded file to Android's package installer.
  ///
  /// Android 8+ requires the "Install unknown apps" permission to be
  /// granted per-app before this will do anything visible — if it's not
  /// granted, [openInstallPermissionSettings] should be called first
  /// (the OS shows its own settings screen; there's no in-app toggle for
  /// this, it's a system-level guard).
  Future<bool> installApk(File apkFile) async {
    final granted = await Permission.requestInstallPackages.isGranted;
    if (!granted) return false;

    final result = await OpenFilex.open(apkFile.path);
    return result.type == ResultType.done;
  }

  Future<bool> hasInstallPermission() =>
      Permission.requestInstallPackages.isGranted;

  /// Opens the OS "install unknown apps" settings screen scoped to SOMS.
  /// permission_handler can't silently grant this one (Android disallows
  /// it) — this just gets the user to the right screen.
  Future<void> openInstallPermissionSettings() async {
    await Permission.requestInstallPackages.request();
  }
}
