<?php

namespace App\Providers;

use App\Models\Security\Authorizations\Permission as AppPermission;
use App\Models\Security\Authorizations\Role as AppRole;
use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Classroom;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\School;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\Messaging\ChannelConfiguration;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Observers\AcademicYearCacheObserver;
use App\Observers\AttendanceObserver;
use App\Observers\ChannelConfigurationCacheObserver;
use App\Observers\NotificationCacheObserver;
use App\Observers\PermissionCacheObserver;
use App\Observers\SchoolCacheObserver;
use App\Observers\StaticCatalogCacheObserver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\TransientToken;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureApiRateLimiters();

        Attendance::observe(AttendanceObserver::class);

        Role::observe(PermissionCacheObserver::class);
        Permission::observe(PermissionCacheObserver::class);
        AppRole::observe(PermissionCacheObserver::class);
        AppPermission::observe(PermissionCacheObserver::class);

        ScolarYear::observe(AcademicYearCacheObserver::class);
        AcademicPeriod::observe(AcademicYearCacheObserver::class);
        School::observe(SchoolCacheObserver::class);

        AcademicNotification::observe(NotificationCacheObserver::class);
        ChannelConfiguration::observe(ChannelConfigurationCacheObserver::class);

        Shift::observe(StaticCatalogCacheObserver::class);
        Nivel::observe(StaticCatalogCacheObserver::class);
        Grade::observe(StaticCatalogCacheObserver::class);
        Area::observe(StaticCatalogCacheObserver::class);
        Subject::observe(StaticCatalogCacheObserver::class);
        Classroom::observe(StaticCatalogCacheObserver::class);
    }

    /**
     * Registra los rate limiters de la API.
     *
     * La clave se resuelve SIEMPRE explícitamente contra el guard sanctum
     * (no contra $request->user()): el throttle puede ejecutarse antes que
     * auth:sanctum según el orden de middleware, y clavear por IP colapsaría
     * a todos los docentes detrás de un mismo NAT (§8.4: límite por token).
     */
    protected function configureApiRateLimiters(): void
    {
        RateLimiter::for('api:login', function (Request $request): Limit {
            return Limit::perMinute((int) config('api.rate_limit.login_per_minute', 5))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api:v1', function (Request $request): Limit {
            return Limit::perMinute((int) config('api.rate_limit.v1_per_minute', 120))
                ->by($this->tokenSignature($request));
        });

        RateLimiter::for('api:sync-push', function (Request $request): Limit {
            return Limit::perMinute((int) config('api.rate_limit.sync_push_per_minute', 60))
                ->by($this->tokenSignature($request));
        });
    }

    /**
     * Firma de rate limiting: id del token Sanctum cuando existe, IP si no.
     */
    protected function tokenSignature(Request $request): string
    {
        $user = $request->user('sanctum');
        $token = $user?->currentAccessToken();

        if ($user !== null && $token !== null && ! $token instanceof TransientToken) {
            return 'token:'.$token->id;
        }

        if ($user !== null) {
            return 'user:'.$user->id;
        }

        return 'ip:'.$request->ip();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
