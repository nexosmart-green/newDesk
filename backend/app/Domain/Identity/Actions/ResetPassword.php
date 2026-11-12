<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/**
 * Forgotten password, step 2: a valid e-mailed token sets a new password. Only active people can reset.
 * A reset after a compromise must also kill every MCP token the attacker may have created.
 */
final class ResetPassword
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @throws BusinessRuleViolation when the token is invalid or expired (field `email`) */
    public function __invoke(string $email, #[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password): void
    {
        $status = Password::broker()->reset(
            ['email' => $email, 'token' => $token, 'password' => $password, 'status' => UserStatus::Active->value],
            fn (User $user, string $newPassword) => DB::transaction(function () use ($user, $newPassword) {
                $user->forceFill(['password' => $newPassword])->setRememberToken(null);
                $user->save();
                $user->tokens()->delete();
                ($this->recordActivity)('user.password_reset', $user, $user);
            }),
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new BusinessRuleViolation('El enlace no es válido o venció. Pedí uno nuevo.', 'invalid_reset_token', field: 'email');
        }
    }
}
