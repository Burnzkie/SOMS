<?php

namespace App\Providers;

use App\Models\EventSession;
use App\Models\Fine;
use App\Models\OfficerPosition;
use App\Models\User;
use App\Policies\AttendanceSessionPolicy;
use App\Policies\FinePolicy;
use App\Policies\OfficerPositionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Policies are registered explicitly here rather than relying on
     * Laravel's naming-convention auto-discovery. Laravel 11 does not
     * ship AuthServiceProvider by default, so this is the single,
     * readable list of "which Policy governs which model" for the
     * whole project — see 01-Overview-Architecture.md Decision 2.24
     * and 03-Auth-Security.md §20.6.
     */
    public function boot(): void
    {
        Gate::policy(Fine::class, FinePolicy::class);
        Gate::policy(EventSession::class, AttendanceSessionPolicy::class);
        Gate::policy(OfficerPosition::class, OfficerPositionPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Paginator::defaultView('vendor.pagination.soms');
        Paginator::defaultSimpleView('vendor.pagination.soms');

        // See 03-Auth-Security.md §20.8. throttle:api is the catch-all floor
        // on the whole routes/api.php v1 group.
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('password-reset', fn (Request $r) => Limit::perMinute(3)->by($r->ip()));
        RateLimiter::for('scan', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));

        // Render terminates SSL upstream and forwards over plain HTTP, so
        // without trustProxies (bootstrap/app.php) + this, Laravel generates
        // http:// URLs (form actions, asset(), route()) and Chrome flags
        // login/forms as "not secure". See 03-Auth-Security.md.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}