# SOMS Mobile — setup

This is a **lib/ drop-in**, not a full `flutter create` output — I don't have
the Flutter SDK in my sandbox, so the `android/`, `ios/`, and platform glue
files aren't generated. Do this once, locally:

```bash
# 1. Generate the platform scaffolding into a fresh folder
flutter create --org com.pac.soms --project-name soms_mobile soms_mobile_app
cd soms_mobile_app

# 2. Overwrite lib/ and pubspec.yaml with the ones from this zip
#    (delete the generated lib/main.dart and lib/ first, then copy these over)

# 3. Install dependencies
flutter pub get

# 4. Run against your local Laravel API
#    Android emulator -> 10.0.2.2 aliases your machine's localhost
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1

# Physical device -> use your machine's LAN IP instead, e.g.:
flutter run --dart-define=API_BASE_URL=http://192.168.1.50:8000/api/v1
```

Make sure `php artisan serve --host=0.0.0.0` is running (not just
`--host=127.0.0.1`) so the emulator/device can actually reach it, and that
`CORS_ALLOWED_ORIGINS` in your `.env` doesn't block it (mobile uses Bearer
tokens, not cookies, so CORS matters less here than for the web app, but
worth checking if you hit connection errors).

## What's wired up

- **Auth**: `POST /auth/login` → Sanctum token in Flutter Secure Storage →
  role-based routing. `must_change_password` gates every dashboard route,
  same as the web app.
- **401 handling**: any API call returning 401 clears the local session and
  bounces back to login, app-wide — not just on login itself.
- **Role shells**: bottom-nav scaffolding for Student / Officer / Admin with
  the nav items from `10-Mobile-Deployment.md`. Every tab is currently a
  `PlaceholderTab` — swap the `builder` in each `*_home_screen.dart` for a
  real screen as it's built; the shell itself doesn't change.

## What's NOT wired up yet (next slices)

- QR display (`qr_flutter`, rendered from `GET /api/v1/student/qr/current`
  — note the payload is `"<user_id>:<token>"`, matching the scan-station fix
  on the Laravel side)
- Officer camera scan screen (`mobile_scanner`) + offline scan queue
  (Flutter Secure Storage, `scan-batch` sync)
- Events, Fines, Announcements, Calendar screens (all three roles)
- Firebase push — `firebase_messaging` is in `pubspec.yaml` but
  `Firebase.initializeApp()` is **not** called in `main.dart` yet, since
  that needs `google-services.json` / `GoogleService-Info.plist` from your
  Firebase console first. Add those, then wire init in `main.dart`.
- Admin screens (Users, Officer Appointment, Reports, Logs) are placeholders

## Project layout

```
lib/
  core/            Dio client, secure storage, API base URL config
  models/          AppUser
  providers/       Riverpod AuthController (login/logout/change-password)
  screens/
    auth/          Login, forced change-password
    student/        officer/        admin/    (nav shells)
  widgets/         RoleShell (shared bottom-nav scaffold)
```
