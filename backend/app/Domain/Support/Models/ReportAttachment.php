<?php

namespace App\Domain\Support\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One image of a chat message (D-049), a file on the private disk under reports/YYYY/MM/.
 *
 * @property int $id
 * @property int $company_id
 * @property int $message_id
 * @property string $path
 * @property string $content_type
 * @property int $size
 * @property CarbonImmutable $created_at
 */
class ReportAttachment extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $table = 'support_report_attachments';

    protected $guarded = [];

    protected $dateFormat = Report::DATE_FORMAT;

    protected function casts(): array
    {
        return ['size' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ReportMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ReportMessage::class, 'message_id');
    }

    /** One image of that report's chat; the client never reaches the image of an internal note (D-051). */
    public static function ofReport(Report $report, int $id, bool $withInternal): ?self
    {
        return self::query()->whereKey($id)
            ->whereHas('message', fn (Builder $messages) => $messages->where('report_id', $report->id)->unless($withInternal, fn (Builder $query) => $query->where('internal', false)))
            ->first();
    }
}
