<?php

namespace App\Domain\Assistant\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * RF-14.06: the weekly summary sent to a company (one per week).
 *
 * @property int $id
 * @property int $company_id
 * @property Carbon $week_from
 * @property Carbon $week_to
 * @property string|null $content null while it is being written
 * @property string|null $written_by composing | ai | template
 * @property string $cost_usd
 * @property array<string, array{reached?: int, error?: string}>|null $channels per channel; a missing `reached` is retried
 */
class AssistantWeeklySummary extends Model
{
    use BelongsToCompany;

    public const WRITTEN_BY_AI = 'ai';

    public const WRITTEN_BY_TEMPLATE = 'template';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['week_from' => 'date', 'week_to' => 'date', 'cost_usd' => 'decimal:6', 'channels' => 'array'];
    }
}
