# Route additions

Don't replace your route files — add these lines to the existing ones.

## routes/api.php

Add the import near the top, alongside the other `use App\Http\Controllers\Api\...` lines:

```php
use App\Http\Controllers\Api\AppVersionController;
```

Add this inside the `Route::prefix('v1')->middleware('throttle:api')->group(...)` block,
alongside the other **public** routes (next to `/academic-programs`, *before* the
`auth:sanctum` group — the app needs to check this before login too, e.g. to block
a stale install with `force_update` at the login screen):

```php
    Route::get('/app-version', [AppVersionController::class, 'show']);
```

## routes/admin.php

Add the import at the top:

```php
use App\Http\Controllers\Admin\AppVersionController;
```

Add these two lines alongside the other `admin.*` named routes:

```php
Route::get('/app-versions', [AppVersionController::class, 'index'])->name('admin.app-versions.index');
Route::post('/app-versions', [AppVersionController::class, 'store'])->name('admin.app-versions.store');
Route::post('/app-versions/{appVersion}/deactivate', [AppVersionController::class, 'deactivate'])->name('admin.app-versions.deactivate');
```

## partials/admin-nav.blade.php

Add a nav link wherever the existing admin nav items live (next to Reports /
Activity Logs), e.g.:

```blade
<a href="{{ route('admin.app-versions.index') }}" class="{{ request()->routeIs('admin.app-versions.*') ? 'active' : '' }}">App Versions</a>
```

## After adding routes

```
php artisan route:clear
php artisan migrate
```

(Stale `bootstrap/cache/routes-v7.php` has caused "Route not defined" errors
before in this project even with correct registration — clear the route
cache after this change.)
