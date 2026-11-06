<?php

namespace App\Domain\People\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\People\Models\HourlyRate;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RF-01.11: a new rate closes the previous one the day before it starts. History is never rewritten.
 */
final class AddHourlyRate
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, User $user, int $amountCents, Carbon $validFrom): HourlyRate
    {
        return DB::transaction(function () use ($actor, $user, $amountCents, $validFrom) {
            $current = $user->hourlyRates()->latest('valid_from')->lockForUpdate()->first();

            if ($current !== null && $validFrom->lessThanOrEqualTo($current->valid_from)) {
                throw new BusinessRuleViolation(
                    'La nueva tarifa tiene que empezar después del '.$current->valid_from->format('d/m/Y').'.',
                    'rate_not_after_current',
                );
            }

            $current?->forceFill(['valid_to' => $validFrom->copy()->subDay()])->save();

            $rate = HourlyRate::query()->create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'amount_cents' => $amountCents,
                'currency' => $user->company->currency,
                'valid_from' => $validFrom->toDateString(),
                'created_by' => $actor->id,
            ]);

            ($this->recordActivity)('user.rate_added', $user, $actor,
                $current ? ['amount_cents' => $current->amount_cents, 'valid_from' => $current->valid_from->toDateString()] : null,
                ['amount_cents' => $amountCents, 'valid_from' => $validFrom->toDateString()],
            );

            return $rate;
        });
    }
}
