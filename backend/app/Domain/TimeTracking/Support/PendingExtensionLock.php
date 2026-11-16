<?php

namespace App\Domain\TimeTracking\Support;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\TimeTracking\Models\HourExtensionRequest;

/**
 * RF-05.08: a request is resolved once. Two managers clicking at the same time: the second one waits on the
 * row lock and then sees it already resolved. Call inside the resolving transaction.
 */
final class PendingExtensionLock
{
    /** @throws BusinessRuleViolation when someone already approved or rejected it */
    public function acquire(HourExtensionRequest $request): HourExtensionRequest
    {
        $locked = HourExtensionRequest::query()->forCompany($request->company_id)->lockForUpdate()->findOrFail($request->id);
        self::ensurePending($locked);

        return $locked;
    }

    /** Without a lock: to describe a resolution before it runs (MCP). The lock above re-checks it when it runs. */
    public static function ensurePending(HourExtensionRequest $request): void
    {
        if (! $request->isPending()) {
            throw new BusinessRuleViolation('Ese pedido ya fue resuelto.', 'extension_already_resolved');
        }
    }
}
