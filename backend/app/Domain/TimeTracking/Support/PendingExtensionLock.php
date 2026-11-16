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
        if (! $locked->isPending()) {
            throw new BusinessRuleViolation('Ese pedido ya fue resuelto.', 'extension_already_resolved');
        }

        return $locked;
    }
}
