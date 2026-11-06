<?php

namespace App\Domain\Companies\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/**
 * RF-11.01–03. Rules apply to future operations only (CA-11.03): nothing already recorded is revalidated.
 */
final class UpdateCompanySettings
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /**
     * @param  array{name: string, tax_id?: string|null, currency: string, timezone: string}  $data
     * @param  array<string, mixed>  $settings
     */
    public function __invoke(User $actor, Company $company, array $data, array $settings): Company
    {
        return DB::transaction(function () use ($actor, $company, $data, $settings) {
            $before = $this->snapshot($company);

            $company->fill($data);
            $company->settings = [...($company->settings ?? []), ...$settings];
            $company->save();

            ($this->recordActivity)('company.updated', $company, $actor, $before, $this->snapshot($company));

            return $company;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(Company $company): array
    {
        return [
            'name' => $company->name,
            'tax_id' => $company->tax_id,
            'currency' => $company->currency,
            'timezone' => $company->timezone,
            'settings' => $company->settings,
        ];
    }
}
