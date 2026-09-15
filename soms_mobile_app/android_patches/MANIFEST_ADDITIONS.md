# AndroidManifest.xml additions

Add this one line to `android/app/src/main/AndroidManifest.xml`, alongside
the existing `INTERNET` / `CAMERA` permissions:

```xml
<uses-permission android:name="android.permission.REQUEST_INSTALL_PACKAGES"/>
```

This is a "special" runtime permission — it can't be silently granted like
CAMERA. Android shows its own "Allow SOMS to install unknown apps" settings
screen the first time; `Permission.requestInstallPackages.request()` (via
`permission_handler`, already wired into `UpdateService.openInstallPermissionSettings()`)
takes the user there. There's no custom UI for this — it's an OS-level
settings screen, not something we can build a nicer version of.

## FileProvider — likely nothing to do, but verify

`open_filex` (the plugin used to trigger the install intent) bundles its
own `FileProvider` declaration via manifest merge in recent versions, with
authority `${applicationId}.open_filex.fileProvider`. In most cases this
means **you don't need to declare anything yourself.**

**After adding `open_filex` to pubspec.yaml and running `flutter pub get`,
check the merged manifest before assuming it's fine:**

```powershell
cd android
./gradlew app:processDebugManifest
# then inspect:
# android/app/build/intermediates/merged_manifest/debug/AndroidManifest.xml
```

Look for a `<provider>` entry with `android:authorities` ending in
`.open_filex.fileProvider`. If it's there, you're done — skip the rest of
this section.

**If it's missing or you get a manifest merge conflict** (two providers
claiming the same authority, or the file-picker Intent silently failing on
`OpenFilex.open()`), add this manually to the `<application>` block in
`android/app/src/main/AndroidManifest.xml`:

```xml
<provider
    android:name="androidx.core.content.FileProvider"
    android:authorities="${applicationId}.open_filex.fileProvider"
    android:exported="false"
    android:grantUriPermissions="true">
    <meta-data
        android:name="android.support.FILE_PROVIDER_PATHS"
        android:resource="@xml/file_paths" />
</provider>
```

...and create `android/app/src/main/res/xml/file_paths.xml`
(a starter version is included in this delivery at
`android_patches/xml/file_paths.xml` — copy it to that path) pointing at
the app-local directory `UpdateService.downloadApk()` writes to
(`getApplicationSupportDirectory()`):

```xml
<?xml version="1.0" encoding="utf-8"?>
<paths xmlns:android="http://schemas.android.com/apk/res/android">
    <files-path name="app_support" path="."/>
</paths>
```

Only do this manual step if the automatic merge check above shows it's
actually missing — declaring it when `open_filex` already provides one
causes a build-time authority collision.
