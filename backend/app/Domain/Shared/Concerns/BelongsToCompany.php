<?php

namespace App\Domain\Shared\Concerns;

use App\Domain\Companies\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Every business table carries `company_id` (RF-11). When there is an authenticated user,
 * queries are scoped to their company and new rows inherit it.
 *
 * Out of a request (jobs, commands) there is no user: scope explicitly with `forCompany()`.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $companyId = Auth::user()?->company_id;
            if ($companyId !== null) {
                $builder->where($builder->qualifyColumn('company_id'), $companyId);
            }
        });

        static::creating(function ($model) {
            $model->company_id ??= Auth::user()?->company_id;
        });
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->withoutGlobalScope('company')->where($query->qualifyColumn('company_id'), $companyId);
    }
}
