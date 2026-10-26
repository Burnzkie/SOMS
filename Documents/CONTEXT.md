<!-- D:\soms\CONTEXT.md -->
# SOMS SGO Edition v4.0 — read this first, then open ONLY the file you need

Repos: `soms/` = Laravel 13 backend + Blade web | `soms_mobile_app/` = Flutter (Android APK)
Rule: spec-first. Edit the spec file → bump its footer changelog → add a line to `spec/CHANGELOG.md` → then code.

## Spec (source of truth) — `spec/`  (adjust folder name if yours differs)
- Spec map / what each file covers → spec/00-INDEX.md
- Auth, roles, security rules → spec/03-Auth-Security.md
- Testing and maintenance → spec/11-Testing-Maintenance.md
- Any other module (attendance, fines, officers, events) → find it in 00-INDEX.md
- What changed and when → spec/CHANGELOG.md
- Why a choice was made / known gotchas → decisions.md

## Backend code — `soms/`
- Business logic, models, controllers, jobs → soms/app/
- Web + admin routes (new routes must be added manually) → soms/routes/ (admin.php)
- Tables and migrations → soms/database/migrations/
- Blade UI (`<x-layout>`, shared classes in app.blade.php) → soms/resources/views/
- Tests (`php artisan test`, 24 passing) → soms/tests/
- Deploy: Render + FrankenPHP → soms/Dockerfile, soms/render.yaml, soms/docker/
- CI → soms/.github/

## Mobile code — `soms_mobile_app/`
- App source (Riverpod, Dio) → soms_mobile_app/lib/
- Version bump / dependencies → soms_mobile_app/pubspec.yaml
- Android signing and manifest → soms_mobile_app/android/

## Do not read unless asked
- soms/vendor, soms/storage, soms_mobile_app/build, .dart_tool, soms/routes_patches, android_patches (old delivery patches)
