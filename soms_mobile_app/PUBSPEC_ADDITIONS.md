# pubspec.yaml additions

Add these three to the `dependencies:` block (versions are latest-stable
as of Sep 2026 — bump if `flutter pub get` finds newer):

```yaml
  # In-app auto-update — reads the installed build number to compare
  # against GET /api/v1/app-version.
  package_info_plus: ^8.0.0

  # Triggers Android's package installer on the downloaded APK.
  open_filex: ^4.5.0

  # Requests the "install unknown apps" special permission.
  permission_handler: ^11.3.1

  # Already a transitive dependency of several plugins, but declare it
  # directly since UpdateService calls it explicitly.
  path_provider: ^2.1.3
```

Then:
```powershell
flutter pub get
```

Note the project already flagged elsewhere that dependency versions run
a bit behind what's available — that's an existing, unrelated situation
(`flutter pub outdated` lists ~37 packages behind, e.g. `flutter_riverpod`
2.6.1 vs 3.4.3 available). Don't chase those upgrades as part of this
change — riverpod 2.x → 3.x is a breaking migration, out of scope here.
