<?php

namespace App\Providers;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Listeners\SendDomainNotifications;
use App\Domain\People\Contracts\AvatarProcessor;
use App\Domain\Shared\Activity\ActivityContext;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Listeners\StopTimersOnDomainEvents;
use App\Domain\TimeTracking\Support\HourBagRegistry;
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
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

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
        Route::bind('user', fn (string $value) => User::query()
            ->where('company_id', Auth::user()?->company_id)
            ->findOrFail((int) $value));

        // Tasks are addressed by id or by their human code (`bhn-60`, used in commits and by MCP).
        Route::bind('task', fn (string $value) => Task::query()
            ->where(ctype_digit($value) ? 'id' : 'code', ctype_digit($value) ? (int) $value : mb_strtolower($value))
            ->firstOrFail());
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));

        // NexoSmart standard: at most one e-mail per second (Resend).
        RateLimiter::for('mail', fn () => Limit::perSecond(1));
    }
}
