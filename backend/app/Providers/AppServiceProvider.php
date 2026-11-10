<?php

namespace App\Providers;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Contracts\AgentGateway;
use App\Domain\Monitoring\Contracts\DiscordGateway;
use App\Domain\Monitoring\Discord\Notifier;
use App\Domain\Monitoring\Events\AlertChanged;
use App\Domain\Monitoring\Events\VulnerabilitiesScanned;
use App\Domain\Notifications\Listeners\SendDomainNotifications;
use App\Domain\People\Contracts\AvatarProcessor;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\ActivityContext;
use App\Domain\Support\Contracts\ClaudeRunner;
use App\Domain\Support\Events\ReportSubmitted;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Listeners\StopTimersOnDomainEvents;
use App\Domain\TimeTracking\Support\HourBagRegistry;
use App\Http\Responses\ApiResponse;
use App\Infrastructure\Agent\CurlAgentGateway;
use App\Infrastructure\Claude\UnixSocketClaudeRunner;
use App\Infrastructure\Discord\DiscordBotGateway;
use App\Infrastructure\Discord\MutedDiscordGateway;
use App\Infrastructure\Images\GdAvatarProcessor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ActivityContext::class);
        $this->app->scoped(HourBagRegistry::class);
        $this->app->bind(AvatarProcessor::class, GdAvatarProcessor::class);
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

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        Event::subscribe(StopTimersOnDomainEvents::class);
        Event::subscribe(SendDomainNotifications::class);

        $this->registerAuthorization();
        $this->registerRouteBindings();
        $this->registerRateLimiters();
    }

    private function registerAuthorization(): void
    {
        // Every granular permission is a Gate ability: `$user->can('hours.allocate')`.
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }

        // App\Domain\<Module>\Models\X  →  App\Domain\<Module>\Policies\XPolicy
        Gate::guessPolicyNamesUsing(fn (string $modelClass) => str_replace('\\Models\\', '\\Policies\\', $modelClass).'Policy');
    }

    private function registerRouteBindings(): void
    {
        // Users have no global company scope; bind them to the caller's company so others are 404.
        // {user} is a team member and {clientUser} a client's portal user: no team route reaches the other kind.
        Route::bind('user', fn (string $value) => User::query()
            ->where('company_id', Auth::user()?->company_id)->team()
            ->findOrFail((int) $value));
        Route::bind('clientUser', fn (string $value) => User::query()
            ->where('company_id', Auth::user()?->company_id)->whereNotNull('client_id')
            ->findOrFail((int) $value));

        // Monitoring's project screens (RF-13.06): only client projects have a status page; internal ones are 404.
        Route::bind('clientProject', fn (string $value) => Project::query()->whereNotNull('client_id')->findOrFail((int) $value));

        // Tasks are addressed by id or by their human code (`bhn-60`, used in commits and by MCP).
        Route::bind('task', fn (string $value) => (ctype_digit($value)
            ? Task::query()->whereKey((int) $value)
            : Task::query()->withCode($value)
        )->firstOrFail());
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);

        // Keyed by the pending login (server-side identity), never by client-supplied input.
        RateLimiter::for('two-factor', fn (Request $request) => [
            Limit::perMinute(5)->by('2fa|'.(($request->hasSession() ? $request->session()->get('login.pending.user_id') : null) ?? $request->ip())),
            Limit::perMinute(30)->by('2fa-ip|'.$request->ip()),
        ]);

        // Invitations and password reset: per IP, separate from the login buckets.
        RateLimiter::for('guest', fn (Request $request) => Limit::perMinute(20)->by('guest|'.$request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));

        // NexoSmart standard: at most one e-mail per second (Resend).
        RateLimiter::for('mail', fn () => Limit::perSecond(1));

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
    }
}
