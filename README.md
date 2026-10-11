<!-- README.md -->

# SOMS — Student Organization Management System (SGO Edition)

A web + Android platform that runs the **Student Government Organization (SGO)** of Philippine Advent College (Sindangan, Zamboanga del Norte). It replaces paper attendance sheets and manual fine tracking with QR-based attendance, automatic fine issuance, and a single admin workspace.

Built as a capstone/thesis project. Serves roughly 800 student members.

> **Version:** SGO Edition v4.0 · **Backend:** Laravel 13 · **Mobile:** Flutter (Android APK)

---

## What it does

| Module | Summary |
|---|---|
| **Student registration & membership** | Students register with a `student_id` (pattern `^[A-Za-z]\d{10}$`) and wait for admin approval. |
| **QR-based attendance** | Each student has a rotating QR code (60-second window). Officers scan it per event session, from the web scan station or the mobile app. Supports an offline scan queue with batch sync. |
| **Fine management** | Absence fines are issued automatically per session. Only the Treasurer and Admin can view, mark paid, or waive fines. |
| **Officer appointment** | Admin appoints officers directly (no elections). Positions: President, VP, Secretary, Treasurer, Auditor, PRO. |
| **Announcements, calendar, notifications** | Events appear on a calendar; students are notified of announcements and fines. |
| **Admin dashboard & system health** | User approval, reports, activity logs (hash-chained for tamper evidence), health checks, and in-app APK version publishing. |

Electronic voting/elections and Sports Fest were retired in v4.0 and removed from the codebase.

### How events and fines work

- An event has one or more days. Each day has **sessions**: Morning/Afternoon (full-day), a single session (short event), or custom named sessions.
- Each session has its own **time-in** and **time-out** windows. Time-out is optional.
- A day can be flagged as a **parade** day, which applies a higher fine to the Morning time-in.
- Events can target specific **programs**. Students outside the target programs are not fined and their scans are rejected. No programs selected means all programs.
- Draft (unpublished) events never issue fines.

Fines are issued by `IssueSessionFinesJob`, triggered two ways:

1. A scheduled command (`IssueDueSessionFines`) runs every 15 minutes and picks up sessions whose windows have closed.
2. An officer clicks **Close Session**, which forces issuance immediately.

Issuance is idempotent (`firstOrCreate`), so running it twice never creates duplicate fines.

---

## Tech stack

**Backend**
- Laravel 13 on FrankenPHP
- Laravel Sanctum (cookie sessions for web, Bearer tokens for mobile)
- MySQL (Clever Cloud in production, XAMPP locally)
- Queue on the `database` driver, no Redis
- Cloudflare R2 for file and APK storage
- DomPDF, Intervention Image v3, simple-qrcode
- Hosted on Render; GitHub Actions for CI

**Mobile**
- Flutter, Riverpod, Dio
- Flutter Secure Storage (token and offline scan queue)
- `qr_flutter`, `mobile_scanner`, `table_calendar`, `connectivity_plus`
- In-app auto-update (`package_info_plus`, `open_filex`, `permission_handler`)
- Sentry for error tracking

---

## Repository layout

```
.
├── app/                    Laravel application code
│   ├── Jobs/               IssueSessionFinesJob
│   ├── Console/Commands/   IssueDueSessionFines
│   └── Http/Controllers/   Admin\, Officer\, Api\ controllers
├── routes/
│   ├── admin.php           Admin routes (add new admin routes here)
│   └── console.php         Scheduled commands
├── resources/views/        Blade UI (<x-layout> component pattern)
├── tests/                  Automated test suite
├── render.yaml             Render blueprint
├── start.sh                Container entrypoint (web + queue worker + scheduler)
└── soms_mobile_app/        Flutter app
    └── lib/
        ├── core/           Dio client, secure storage, API config
        ├── models/
        ├── providers/      Riverpod controllers
        ├── screens/        auth/, student/, officer/, admin/
        └── widgets/        Shared widgets (RoleShell)
```

Adjust the tree above if your folder names differ.

---

## Getting started (local development)

### Prerequisites

- PHP 8.3+, Composer
- MySQL (XAMPP works)
- Node.js (if you build front-end assets)
- Flutter SDK and Android Studio (for the mobile app)

### 1. Backend

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your local database settings, then:

```bash
php artisan migrate
php artisan db:seed --class=DevSeeder   # optional: sample admin/officers/students/events
php artisan serve --host=0.0.0.0
```

`--host=0.0.0.0` lets a phone or emulator on your network reach the API.

### 2. Queue worker and scheduler

Fines are issued in the background, so **both** of these must be running locally, in separate terminals:

```bash
php artisan queue:work
php artisan schedule:work
```

Without the queue worker, jobs sit in the `jobs` table and never run. Without the scheduler, the 15-minute fine check never fires (the officer's **Close Session** button still works).

### 3. Mobile app

```bash
cd soms_mobile_app
flutter pub get

# Android emulator (10.0.2.2 is the host machine's localhost)
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1

# Physical device on the same Wi-Fi (use your PC's LAN IP)
flutter run --dart-define=API_BASE_URL=http://192.168.1.50:8000/api/v1
```

Release builds **must** pass `API_BASE_URL` too, or the app has no valid API address:

```bash
flutter build apk --release --dart-define=API_BASE_URL=https://<your-api-host>/api/v1
```

### 4. Run the tests

```bash
php artisan test
```

---

## Configuration

Copy `.env.example` to `.env`. Key variables:

| Variable | Purpose |
|---|---|
| `APP_KEY`, `APP_URL` | Standard Laravel settings |
| `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection |
| `QUEUE_CONNECTION` | `database` |
| `FILESYSTEM_DISK` | `r2` in production (Render's disk is ephemeral) |
| `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT`, `R2_PUBLIC_URL` | Cloudflare R2 |
| `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS` | Web/mobile access |
| `SENTRY_LARAVEL_DSN` | Error tracking |
| `SEED_DEV_DATA` | `true` seeds demo data on boot (remove before real production use) |
| `SEED_ADMIN` | `true` creates the first admin on boot; read the password from the deploy log, then set back to `false` |

**Never commit** `.env`, `key.properties`, or the release keystore (`.jks`).

---

## Deployment

### Backend (Render)

- Builds from the `Dockerfile` and starts with `start.sh`.
- `start.sh` runs migrations, then launches, in one container:
  - the queue worker (in a restart loop)
  - a scheduler loop (`schedule:run` every minute)
  - FrankenPHP
- Do **not** also deploy the separate worker service from `render.yaml`; each extra process uses a database connection.
- **Clever Cloud MySQL allows 5 connections.** Keep FrankenPHP at `num_threads 3` so the web, worker, and scheduler stay under the cap.
- The free Render tier sleeps after inactivity, and background loops sleep with it. Use an always-on instance or an uptime pinger so scheduled fines run on time.

### Mobile app (Android APK)

The app is distributed as an APK, not through the Play Store.

1. Bump `version: X.Y.Z+N` in `pubspec.yaml`. The build number `N` must increase every release.
2. Build with the release keystore and the `API_BASE_URL` define (see above).
3. Verify the signature with `apksigner verify`.
4. Upload the APK to R2.
5. In **Admin → App Versions** (`/admin/app-versions`), publish the new version with its `versionCode`, `versionName`, and APK URL. Use *force update* sparingly.
6. Students get an update dialog on next launch. Updates install over the old app without uninstalling, as long as the signing key and package name stay the same.

**Back up your keystore.** If it is lost, existing installs can never be updated in place.

A public download page is available at `/download`.

---

## API at a glance

- Web routes use Sanctum cookie sessions; mobile uses Bearer tokens under `/api/v1`.
- `GET /api/v1/app-version` is public (outside `auth:sanctum`) so forced updates work at the login screen.
- Any `401` from the API clears the local session in the app and returns to login.

---

## Security notes

- Server-side validation on all input; Eloquent / parameterized queries only.
- Fine access is gated by position-level checks (Treasurer and Admin).
- Officer permission tiers return `null` for users with no active position, never a default tier.
- Auth and registration endpoints are rate-limited; scan endpoints use a throttle limiter and a unique index to block duplicate scans.
- The activity log is hash-chained and can be verified (`logs:verify` runs daily).
- Student QR tokens rotate every 60 seconds.
- Secrets and credential-bearing request bodies are never logged.

---

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| No fines issued, no error | Queue worker or scheduler not running; event still Draft; student not approved or not in the event's target programs; missing fine rule for the session's violation type |
| `Too many connections` | More than 5 simultaneous DB connections; lower FrankenPHP threads |
| Update dialog never appears | Build number not higher than the installed one, or the version isn't active in App Versions |
| "App not installed" on update | APK signed with a different key than the installed app |
| App stuck on the logo screen | Release built without `--dart-define=API_BASE_URL=...`, or unreadable old secure-storage data |
| APK download fails with `DNS_PROBE_FINISHED_NXDOMAIN` | The network's DNS can't resolve the `r2.dev` host; switch DNS to `1.1.1.1` or serve the APK through a custom domain |

---

## Author

**Jude Burnea** — sole developer · [GitHub: Burnzkie](https://github.com/Burnzkie)

Built for the Student Government Organization of Philippine Advent College.
