/// Mirrors GET /api/v1/app-version's `data` object (10-Mobile-Deployment.md
/// Part B). Nullable fields match the endpoint returning `data: null` when
/// no version has ever been published for the platform.
class AppVersion {
  const AppVersion({
    required this.versionCode,
    required this.versionName,
    required this.apkUrl,
    this.changelog,
    required this.forceUpdate,
    this.minSupportedVersionCode,
  });

  final int versionCode;
  final String versionName;
  final String apkUrl;
  final String? changelog;
  final bool forceUpdate;
  final int? minSupportedVersionCode;

  factory AppVersion.fromJson(Map<String, dynamic> json) => AppVersion(
        versionCode: json['versionCode'] as int,
        versionName: json['versionName'] as String,
        apkUrl: json['apkUrl'] as String,
        changelog: json['changelog'] as String?,
        forceUpdate: json['forceUpdate'] as bool? ?? false,
        minSupportedVersionCode: json['minSupportedVersionCode'] as int?,
      );
}
