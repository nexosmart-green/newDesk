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
        $message = self::authorization($e)?->response()?->message();

        return $message !== null && $message !== '' && $message !== 'This action is unauthorized.' ? $message : null;
    }

    /** The stable rule code a policy gave with Response::deny('…', 'code') (same role as BusinessRuleViolation::$ruleCode). */
    public static function codeOf(Throwable $e): ?string
    {
        $code = self::authorization($e)?->response()?->code();

        return is_string($code) && $code !== '' ? $code : null;
    }

    private static function authorization(Throwable $e): ?AuthorizationException
    {
        $authorization = $e instanceof AuthorizationException ? $e : $e->getPrevious();

        return $authorization instanceof AuthorizationException ? $authorization : null;
    }
}
