# SOMS — Student Organization Management System

SOMS is a Laravel web application built for Philippine Advent College's
Student Government Organization (SGO) in Sindangan, Zamboanga del Norte.
It manages officer/student accounts, events and QR-based attendance,
fines, announcements, and the SGO's electronic voting process, with
separate dashboards for **Admin**, **Officer**, and **Student** roles.

A companion Flutter mobile app (`soms_mobile_app/`, separate repo) consumes
this project's REST API for on-the-go attendance scanning and dashboards.

## What the system can do

- **Role-based accounts** — Admin, Officer, and Student, each with their
  own dashboard and permissions (see `app/Policies/`).
- **Student self-registration** — new accounts start `is_approved = false`
  and wait on an Admin to approve them before logging in.
- **Officer appointment** — Admins assign officer positions
  (`OfficerPosition`).
- **Events & attendance** — multi-day events (`Event`, `EventDay`,
  `EventSession`) with QR-code-based check-in/out (`EventAttendance`),
  plus an attendance-delegate feature for officers who can't personally
  scan.
- **Fines** — automatic fine rules per event (`EventFineRule`) and
  per-student fine tracking (`Fine`).
- **Announcements** — org-wide or targeted announcements.
- **Calendar** — shared SGO calendar entries.
- **Notifications** — in-app notifications per user.
- **Activity log** — an audit trail of account and admin actions
  (`ActivityLog`).
- **Electronic voting** — the SGO's primary capstone feature (see
  `app/Models/Organization*`).
- **REST API** (`routes/api.php`, prefixed `/api/v1`) — Sanctum
  bearer-token auth, used by the Flutter mobile app; self-service
  endpoints for profile editing, avatar upload, and password change.

## Requirements

Install these before setting up the project:

| Requirement | Version | Notes |
|---|---|---|
| PHP | ^8.2 | with the extensions Laravel needs by default (mbstring, openssl, pdo, tokenizer, xml, ctype, json, bcmath, fileinfo) |
| Composer | 2.x | PHP dependency manager |
| Node.js | 18+ recommended | for the Vite/Tailwind frontend build |
| npm | bundled with Node | |
| A database | MySQL/MariaDB (recommended) or SQLite | see `.env` — `DB_CONNECTION` |

Optional:
- **Docker** — a `Dockerfile` and `docker/start.sh` are included if you'd
  rather containerize than run locally (see [Docker](#docker) below).

## Key libraries this project uses

Installed automatically by `composer install` / `npm install` below —
listed here so you know what's pulling in what:

**Backend (Composer / `composer.json`)**
- `laravel/framework` ^13.0 — the framework itself
- `laravel/sanctum` ^4.3 — API token auth (used by the mobile app and
  self-service endpoints)
- `laravel/tinker` ^3.0 — REPL for debugging (`php artisan tinker`)
- `intervention/image` 3.0 — avatar image resizing/processing
- `simplesoftwareio/simple-qrcode` ^4.2 — QR code generation for
  event attendance

Dev-only: `laravel/pint` (code style), `laravel/sail` (optional Docker
dev environment), `laravel/pail` (log tailing), `phpunit`, `mockery`,
`fakerphp/faker`, `nunomaduro/collision`.

**Frontend (npm / `package.json`)**
- `vite` — dev server & build tool
- `laravel-vite-plugin` — Laravel/Vite integration
- `tailwindcss` + `autoprefixer` + `postcss` — styling
- `axios` — HTTP calls from Blade/JS views
- `concurrently` — runs multiple dev processes side by side (used by
  `composer run dev`, if present in `composer.json`'s `scripts`)

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

Then create the database (e.g. via a MySQL client or phpMyAdmin) before
migrating.

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

This project ships two separate seeders — use the right one for your
situation:

**Local development** — creates a full set of demo data (admin, several
officers, students, sample events). Only runs outside `production`:

```bash
php artisan db:seed --class=DevSeeder
```
Default dev admin login: `student_id: A0000000000` / `password: password`
— you'll be asked to change this if `must_change_password` is enforced,
per `03-Auth-Security.md`.

**Production / real deployment** — creates exactly one admin with a
random generated password (printed once to the console), safe to run
anywhere:

```bash
php artisan db:seed --class=AdminSeeder
```
Optional env vars before running it: `ADMIN_STUDENT_ID`, `ADMIN_EMAIL`,
`ADMIN_NAME` (see `database/seeders/AdminSeeder.php`).

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

- **`APP_URL`** — set this to your real domain in production; several
  generated links (password reset, QR codes) depend on it.
- **`SANCTUM_TOKEN_EXPIRATION_MINUTES`** — mobile app bearer tokens
  expire after this many minutes (defaults to 43200 = 30 days).
- **HTTPS behind a reverse proxy** (Render, etc.) — if you deploy behind
  a proxy that terminates SSL, make sure `bootstrap/app.php` has
  `trustProxies` configured and `AppServiceProvider::boot()` calls
  `URL::forceScheme('https')` in production, or generated URLs (form
  actions, asset links) will come out as `http://` and trigger browser
  "not secure" warnings.

## Docker

A `Dockerfile` and `docker/start.sh` are included for containerized
deployment (e.g. Render, Railway, Fly.io). `docker/start.sh` runs
`config:cache`, `route:cache`, `migrate --force`, and `storage:link` on
every boot, plus two optional env-var-gated seed steps:

```env
SEED_DEV_DATA=true   # runs DevSeeder on boot — demo data, local/staging only
SEED_ADMIN=true       # runs AdminSeeder on boot — safe for production, idempotent
```
Set the relevant flag to `true`, deploy, check your platform's deploy
logs for output, then set it back to `false` (or remove it) and redeploy.

## Roles & permissions

Authorization is handled through Laravel Policies, registered explicitly
in `app/Providers/AppServiceProvider.php` (Laravel 11+ doesn't
auto-discover them by convention):

| Model | Policy |
|---|---|
| `Fine` | `FinePolicy` |
| `EventSession` | `AttendanceSessionPolicy` |
| `OfficerPosition` | `OfficerPositionPolicy` |
| `User` | `UserPolicy` |

## Mobile app

The Flutter mobile app (`soms_mobile_app`) is a separate project that
talks to this app's `/api/v1` routes over Sanctum bearer tokens. See its
own README for setup — it needs `API_BASE_URL` pointed at wherever this
backend is running (e.g. `http://10.0.2.2:8000/api/v1` for an Android
emulator hitting `php artisan serve` on localhost).

## License

The Laravel framework is open-sourced software licensed under the
[MIT license](https://opensource.org/licenses/MIT).
