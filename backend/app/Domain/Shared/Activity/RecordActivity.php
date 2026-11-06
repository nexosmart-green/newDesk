<?php

namespace App\Domain\Shared\Activity;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The single entry point to write the audit trail. Call it from domain actions,
 * inside the same transaction as the change it describes.
 */
final class RecordActivity
{
    public function __construct(private readonly ActivityContext $context) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function __invoke(
        string $event,
        Model $subject,
        ?User $actor,
        ?array $before = null,
        ?array $after = null,
        ?int $projectId = null,
    ): ActivityLog {
        return ActivityLog::query()->create([
            'company_id' => $subject->getAttribute('company_id') ?? $actor?->company_id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'project_id' => $projectId,
            'actor_id' => $actor?->id,
            'source' => $actor === null ? ActivitySource::System : $this->context->source,
            'token_name' => $this->context->tokenName,
            'event' => $event,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
