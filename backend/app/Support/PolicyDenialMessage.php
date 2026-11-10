<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Throwable;

/**
 * The human reason a policy gave with Response::deny('…'), if any. Laravel's generic default
 * ("This action is unauthorized.") is not a reason and is ignored.
 */
final class PolicyDenialMessage
{
    public static function of(Throwable $e): ?string
    {
        $authorization = $e instanceof AuthorizationException ? $e : $e->getPrevious();
        if (! $authorization instanceof AuthorizationException) {
            return null;
        }

        $message = $authorization->response()?->message();

        return $message !== null && $message !== '' && $message !== 'This action is unauthorized.' ? $message : null;
    }
}
