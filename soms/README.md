# SOMS — Student Organization Management System

SOMS is a Laravel web application built for Philippine Advent College's Student Government Organization (SGO) in Sindangan, Zamboanga del Norte. It manages officer/student accounts, events and QR-based attendance, fines, announcements, and the SGO's electronic voting process, with separate dashboards for Admin, Officer, and Student roles.

A companion Flutter mobile app (`soms_mobile_app/`, separate repo) consumes this project's REST API for on-the-go attendance scanning and dashboards.

## What the system can do

- **Role-based accounts** — Admin, Officer, and Student, each with their own dashboard and permissions (see `app/Policies/`).
- **Student self-registration** — new accounts start `is_approved = false` and wait on an Admin to approve them before logging in.
- **Officer appointment** — Admins assign officer positions (`OfficerPosition`).
- **Events & attendance** — multi-day events (`Event`, `EventDay`, `EventSession`) with QR-code-based check-in/out (`EventAttendance`), plus an attendance-delegate feature for officers who can't personally scan.
- **Fines** — automatic fine rules per event (`EventFineRule`) and per-student fine tracking (`Fine`).
- **Announcements** — org-wide or targeted announcements.
- **Calendar** — shared SGO calendar entries.
- **Notifications** — in-app notifications per user.
- **Activity log** — an audit trail of account and admin actions (`ActivityLog`), hash-chained for tamper-evidence (verified via `php artisan logs:verify`).
- **Electronic voting** — the SGO's primary capstone feature (see `app/Models/Organization*`).
- **REST API** (`routes/api.php`, prefixed `/api/v1`) — Sanctum bearer-token auth, used by the Flutter mobile app; self-service endpoints for profile editing, avatar upload, and password change.

## Requirements

Install these before setting up the project:

| Requirement | Version | Notes |
|---|---|---|
| PHP | ^8.2 | with the extensions Laravel needs by default (`mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`) |
| Composer | 2.x | PHP dependency manager |
| Node.js | 18+ recommended | for the Vite/Tailwind frontend build |
| npm | bundled with Node | |
| A database | MySQL/MariaDB (recommended) or SQLite | see `.env` — `DB_CONNECTION` |

**Optional:** Docker — a `Dockerfile` and `docker/start.sh` are included if you'd rather containerize than run locally (see [Docker](#docker) below).

## Key libraries this project uses

Installed automatically by `composer install` / `npm install` below — listed here so you know what's pulling in what.

**Backend** (Composer / `composer.json`)

- `laravel/framework` ^13.0 — the framework itself
- `laravel/sanctum` ^4.3 — API token auth (used by the mobile app and self-service endpoints)
- `laravel/tinker` ^3.0 — REPL for debugging (`php artisan tinker`)
- `intervention/image` 3.0 — avatar image resizing/processing
- `simplesoftwareio/simple-qrcode` ^4.2 — QR code generation for event attendance
- Dev-only: `laravel/pint` (code style), `laravel/sail` (optional Docker dev environment), `laravel/pail` (log tailing), `phpunit`, `mockery`, `fakerphp/faker`, `nunomaduro/collision`

**Frontend** (npm / `package.json`)

- `vite` — dev server & build tool
- `laravel-vite-plugin` — Laravel/Vite integration
- `tailwindcss` + `autoprefixer` + `postcss` — styling
- `axios` — HTTP calls from Blade/JS views
- `concurrently` — runs multiple dev processes side by side (used by `composer run dev`, if present in `composer.json`'s scripts)

## Installation

```bash
# 1. Clone and enter the project
git clone <your-repo-url> soms
cd soms

# 2. Install PHP dependencies
composer install

# 3. Install frontend dependencies
npm install

# 4. Copy the example environment file
cp .env.example .env

# 5. Generate the app encryption key
php artisan key:generate
```

### Configure your database

Edit `.env` and set your database connection. For MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=soms
DB_USERNAME=root
DB_PASSWORD=
```

Then create the database (e.g. via a MySQL client or phpMyAdmin) before migrating.

For a quick local run without installing MySQL, SQLite works too:

```env
DB_CONNECTION=sqlite
```
```bash
touch database/database.sqlite
```

### Run migrations

```bash
php artisan migrate
```

### Create your first admin account

This project ships two separate seeders — use the right one for your situation:

**Local development** — creates a full set of demo data (admin, several officers, students, sample events). Only runs outside production:

```bash
php artisan db:seed --class=DevSeeder
```

Default dev admin login: `student_id: A0000000000` / `password: password` — you'll be asked to change this if `must_change_password` is enforced, per `03-Auth-Security.md`.

**Production / real deployment** — creates exactly one admin with a random generated password (printed once to the console), safe to run anywhere:

```bash
php artisan db:seed --class=AdminSeeder
```

Optional env vars before running it: `ADMIN_STUDENT_ID`, `ADMIN_EMAIL`, `ADMIN_NAME` (see `database/seeders/AdminSeeder.php`).

### Link storage (for avatars/uploads)

```bash
php artisan storage:link
```

### Build frontend assets

```bash
npm run build
```

Or, for local development with hot reload:

```bash
npm run dev
```

### Serve the app

```bash
php artisan serve
```

Visit `http://localhost:8000`.

## Environment notes

- **`APP_URL`** — set this to your real domain in production; several generated links (password reset, QR codes) depend on it.
- **`APP_DEBUG`** — must be `false` in production. When `true`, unhandled errors dump full stack traces (including file paths and query bindings) straight into the browser response — fine for local dev, a real information leak if it's ever left on for a deployed instance.
- **`SANCTUM_TOKEN_EXPIRATION_MINUTES`** — mobile app bearer tokens expire after this many minutes (defaults to `43200` = 30 days).
- **`SESSION_SECURE_COOKIE`** — should be explicitly set to `true` in production so the session cookie is only ever sent over HTTPS. Left unset, it falls back to Laravel's default rather than being enforced.
- **HTTPS behind a reverse proxy** (Render, etc.) — if you deploy behind a proxy that terminates SSL, make sure `bootstrap/app.php` has `trustProxies` configured and `AppServiceProvider::boot()` calls `URL::forceScheme('https')` in production, or generated URLs (form actions, asset links) will come out as `http://` and trigger browser "not secure" warnings.

## Docker

A `Dockerfile` and `docker/start.sh` are included for containerized deployment (e.g. Render, Railway, Fly.io). `docker/start.sh` runs `config:cache`, `route:cache`, `migrate --force`, and `storage:link` on every boot, plus two optional env-var-gated seed steps:

```env
SEED_DEV_DATA=true   # runs DevSeeder on boot — demo data, local/staging only
SEED_ADMIN=true      # runs AdminSeeder on boot — safe for production, idempotent
```

Set the relevant flag to `true`, deploy, check your platform's deploy logs for output, then set it back to `false` (or remove it) and redeploy.

> **Known tradeoff:** the container currently serves the app with `php artisan serve` — Laravel's single-threaded development server, not built for concurrent production traffic. It's adequate at capstone-demo scale on a resource-constrained free-tier instance, but the honest long-term fix is Laravel Octane (FrankenPHP or Swoole) or a proper nginx + php-fpm setup in the image. Tracked as a known gap, not yet scheduled.

## Roles & permissions

Authorization is handled through Laravel Policies, registered explicitly in `app/Providers/AppServiceProvider.php` (Laravel 11+ doesn't auto-discover them by convention):

| Model | Policy |
|---|---|
| `Fine` | `FinePolicy` |
| `EventSession` | `AttendanceSessionPolicy` |
| `OfficerPosition` | `OfficerPositionPolicy` |
| `User` | `UserPolicy` |

## Security

Baseline protections currently in place, documented here in one place rather than scattered across commit messages.

**Security headers** — `app/Http/Middleware/SecurityHeaders.php` is registered globally (web + API) via `bootstrap/app.php` and adds `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, and `Strict-Transport-Security` (sent only over actual HTTPS) to every response. The CSP allow-list currently covers Google Fonts, the FullCalendar CDN script used on the officer calendar page, and HTTPS images (R2-hosted avatars). It keeps `'unsafe-inline'` for scripts/styles since the Blade views use inline `<script>`/`style=` in a few places — moving to a nonce-based policy is a future tightening pass, not done yet. If you add a new external script/font/image host anywhere, update the allow-list in this file or it'll get silently blocked by the browser.

**Login rate limiting** — a dedicated `login` RateLimiter (`app/Providers/AppServiceProvider.php`) caps attempts at 5/minute, keyed by `student_id + IP` together rather than IP alone, so a shared computer lab IP doesn't lock out every student because one of them mistyped a password, while repeated guesses against one specific account still get capped. Applied to both `POST /login` (web) and `POST /api/v1/auth/login`. This is separate from the general `throttle:api` (120/min) and `throttle:password-reset` (3/min) limiters, which still apply on top of it.

**Input validation** — mostly covered via two `FormRequest` classes (`LoginRequest`, `RegisterRequest`) plus inline `$request->validate()` calls across most controllers. Known gap: a handful of read-only endpoints (search, reports, calendar filters) accept query params without formal validation rules — low severity since they go through Eloquent's parameter binding either way, but worth tightening before treating every input path as fully validated.

**CI** — `.github/workflows/ci.yml` runs on every push/PR: the PHPUnit suite (in-memory SQLite, same as `phpunit.xml` configures), a Pint style check, and `composer audit` for known dependency vulnerabilities. The audit step currently has `continue-on-error: true` — it reports findings but won't fail the build until there's an actual process in place for triaging advisories.

**Automated backups** — `.github/workflows/backup.yml` runs weekly (Sundays 18:00 UTC) plus supports manual triggering from the Actions tab. It `mysqldump`s the production database, gzips it, and uploads it to the same R2 bucket used for avatars, under a `backups/` prefix. Requires these repo secrets (Settings → Secrets and variables → Actions) — separate from your local `.env`, GitHub Actions can't read that file:

- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `R2_ENDPOINT`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`

> Note: if your Clever Cloud MySQL add-on has an IP allowlist, the backup job may fail to connect since GitHub-hosted runners don't have a fixed IP range — test with a manual "Run workflow" click before relying on the Sunday cron. Old backups in the bucket aren't pruned automatically; it'll just grow over time.

**Known gaps / not yet done:**

- Production still runs `php artisan serve` rather than a real app server (see [Docker](#docker) above).
- CSP allows `'unsafe-inline'` rather than using nonces.
- No automated pruning of old database backups.
- `composer audit` findings don't fail CI yet.

## Mobile app

The Flutter mobile app (`soms_mobile_app`) is a separate project that talks to this app's `/api/v1` routes over Sanctum bearer tokens. See its own README for setup — it needs `API_BASE_URL` pointed at wherever this backend is running (e.g. `http://10.0.2.2:8000/api/v1` for an Android emulator hitting `php artisan serve` on localhost). The mobile app stores its Sanctum token via `flutter_secure_storage` (Android `encryptedSharedPreferences`), never in plain `SharedPreferences`.

## License

The Laravel framework is open-sourced software licensed under the MIT license.