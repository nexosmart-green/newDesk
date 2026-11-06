<?php

namespace App\Providers;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Contracts\AgentGateway;
use App\Domain\Monitoring\Contracts\DiscordGateway;
use App\Domain\Monitoring\Discord\Notifier;
use App\Domain\Monitoring\Events\AlertChanged;
use App\Domain\Monitoring\Events\VulnerabilitiesScanned;
use App\Domain\Shared\Activity\ActivityContext;
use App\Domain\Support\Contracts\ClaudeRunner;
use App\Domain\Support\Events\ReportSubmitted;
use App\Http\Responses\ApiResponse;
use App\Infrastructure\Agent\CurlAgentGateway;
use App\Infrastructure\Claude\UnixSocketClaudeRunner;
use App\Infrastructure\Discord\DiscordBotGateway;
use App\Infrastructure\Discord\MutedDiscordGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ActivityContext::class);
        $this->app->singleton(AgentGateway::class, CurlAgentGateway::class);
        $this->app->bind(DiscordGateway::class, fn () => filled($token = config('newdesk.monitoring.discord.bot_token'))
            ? new DiscordBotGateway($token, (string) config('newdesk.monitoring.discord.guild_id'),
                (string) config('newdesk.monitoring.discord.category_id'), config('newdesk.monitoring.discord.channel_categories'))
            : new MutedDiscordGateway);
        $this->app->singleton(ClaudeRunner::class, UnixSocketClaudeRunner::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // RF-13.09: the Discord notices. Queued listeners, apart from Reverb; none queued with notices off (CA-13.20).
        Event::listen(AlertChanged::class, [Notifier::class, 'alert']);
        Event::listen(VulnerabilitiesScanned::class, [Notifier::class, 'vulnerabilities']);
        Event::listen(ReportSubmitted::class, [Notifier::class, 'report']);

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

        // Personal tokens open the MCP endpoints only (as monitoreo's `mon_`): the REST API stays session-only.
        Sanctum::getAccessTokenFromRequestUsing(fn (Request $request) => $request->is('mcp', 'mcp/*') ? $request->bearerToken() : null);
        // RF-12.07, monitoreo's limit: 120 calls a minute per token.
        RateLimiter::for('mcp', function (Request $request) {
            $token = $request->user()?->currentAccessToken();

            return Limit::perMinute(120)->by('mcp:'.($token instanceof PersonalAccessToken ? $token->getKey() : $request->ip()))
                ->response(fn () => ApiResponse::error('Demasiadas llamadas con este token. Probá en un minuto.', 429));
        });
    }
}
