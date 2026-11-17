<?php

namespace App\Domain\Assistant\Support;

use App\Domain\Assistant\Queries\AssistantMonthlySpend;
use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\RateLimiter;

/**
 * RF-14.09/10: the assistant answers only if the Admin turned it on, the company is under its monthly cap
 * and the person is under their hourly turns.
 */
final class AssistantLimits
{
    public function __construct(private readonly AssistantMonthlySpend $spend, private readonly Config $config) {}

    public function ensureCanAsk(User $user): void
    {
        $company = $user->company;
        if (! $company->setting('assistant_enabled')) {
            throw new BusinessRuleViolation('El asistente está apagado. Un administrador lo puede prender en Configuración → Asistente.', 'assistant_disabled', 403);
        }

        $cap = (float) $company->setting('assistant_monthly_budget_usd');
        if (($this->spend)($company) >= $cap) {
            throw new BusinessRuleViolation('El asistente llegó al tope de uso de este mes. Vuelve a estar disponible el 1 del mes que viene, o un administrador puede subir el tope.', 'assistant_monthly_cap', 429);
        }

        $key = $this->rateKey($user);
        $perHour = (int) $this->config->get('newdesk.assistant.turns_per_hour');
        if (RateLimiter::tooManyAttempts($key, $perHour)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            throw new BusinessRuleViolation("Hiciste muchos pedidos seguidos (máximo {$perHour} por hora). Probá de nuevo en {$minutes} minutos.", 'assistant_rate_limited', 429);
        }
        RateLimiter::hit($key, 3600);
    }

    public function turnBudget(Company $company): float
    {
        return (float) $company->setting('assistant_turn_budget_usd');
    }

    private function rateKey(User $user): string
    {
        return "assistant-turns:{$user->id}";
    }
}
