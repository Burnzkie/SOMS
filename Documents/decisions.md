<!-- D:\soms\decisions.md -->
# SOMS — decisions and gotchas

## Scope decisions
- Elections/e-voting and Sports Fest are retired in v4.0 and removed from spec and code. Officers are appointed by Admin directly.
- Free-tier infrastructure is a design constraint, not something to work around.
- APK distribution over PWA: needed for offline QR scan queue, camera scanning roadmap, and push.
- Dark Modern (Vercel-style, violet primary) is the canonical web UI; mobile mirrors it.
- Student QR window stays 60s (5-minute rotation was tried and reverted).

## Deployment
- FrankenPHP on Render: strip capabilities in Docker build (`setcap -r`), or it exits 126.
- Do not add Caddy's `@notFound not_found` block; it is not registered in FrankenPHP's Caddy and breaks Laravel routing.
- Clever Cloud MySQL allows 5 connections. FrankenPHP threads capped at 4.
- Declare `FILESYSTEM_DISK` in render.yaml; keep `QUEUE_CONNECTION=database`.
- Known latency causes: Render/Clever Cloud region mismatch; session/cache on the database driver.

## Auth and security
- `OfficerPermission::tier()` returns `null` when there is no active position (a default tier was an auth bypass).
- `isTreasurer()` is position-level; fines are Treasurer + Admin only.
- `SafeImageUpload` uses Intervention Image v3. Ignore the v2 syntax in 03-Auth-Security.md.
- `IssueSessionFinesJob` violation type must exactly match the enum, or fines silently never issue.
- `GET /api/v1/app-version` stays outside `auth:sanctum` so forced updates work at login.

## Mobile release
- Bump `pubspec.yaml` `version: X.Y.Z+N` before building; it must match the admin app-version form.
- `apksigner.bat` needs `$env:JAVA_HOME` set each PowerShell session.
- Release manifest must declare `INTERNET` and `CAMERA` explicitly.
- Camera scanning (`mobile_scanner`) and FCM are blocked by a Windows CMake conflict.

## Code gotchas
- `public $timestamps = false` on models for tables without timestamps.
- Use `Carbon\Carbon`; there is no Carbon facade.
- Method name is `verifyChainIntegrity()`.
- New admin routes must be manually added to routes/admin.php.
