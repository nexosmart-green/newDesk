<?php

namespace App\Providers;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Contracts\AgentGateway;
use App\Domain\Shared\Activity\ActivityContext;
use App\Http\Responses\ApiResponse;
use App\Infrastructure\Agent\CurlAgentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ActivityContext::class);
        $this->app->singleton(AgentGateway::class, CurlAgentGateway::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceHttps();
        }

        // Every granular permission is a Gate ability: `$user->can('hours.allocate')`,
        // `Gate::authorize(Permission::HoursAllocate->value)` and policies all go through here.
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);

        // Portal login (RF-13.07): monitoreo's per-address and per-IP filters. The per-account
        // budget is counted in portal_login_codes from any IP (D-081, D-087).
        RateLimiter::for('portal-code', function (Request $request) {
            $address = mb_strtolower(trim((string) $request->input('email'))).'|'.$request->ip();

            return [
                Limit::perMinute(1)->by('minute:'.$address),
                Limit::perHour(5)->by('hour:'.$address),
                Limit::perHour(20)->by('ip:'.$request->ip()),
            ];
        });
        RateLimiter::for('portal-verify', fn (Request $request) => Limit::perHour(30)->by($request->ip()));

        // "Reportar un problema" (D-041, D-049), per client: any report POST before its body is read
        // (the 5/hour and 20/day of valid reports are counted on the rows, SubmitReport) and chat messages.
        RateLimiter::for('portal-report-attempts', fn (Request $request) => Limit::perHour(30)->by('report-attempts:'.$request->user()?->client_id)
            ->response(fn () => ApiResponse::error('Demasiados intentos. Esperá un rato o escribinos por mail.', 429)));
        RateLimiter::for('portal-report-messages', fn (Request $request) => Limit::perHour(30)->by('report-messages:'.$request->user()?->client_id)
            ->response(fn () => ApiResponse::error('Mandaste muchos mensajes seguidos. Probá de nuevo en un rato.', 429)));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));
    }
}
