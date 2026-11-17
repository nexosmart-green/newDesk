<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A client's portal user (RF-13.07, D-037): a newDesk user with the "Cliente" role and the client's
 * id, without a password (they sign in with a code by mail). Created from an email, or (de)activated.
 * A deactivated user is out on its next request (EnsureUserIsActive).
 */
final class SaveClientUser
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** Stored lowercased: the portal login matches the address byte for byte against lowercased input (D-076). */
    public function create(Client $client, string $email, User $actor): User
    {
        $email = mb_strtolower(trim($email));

        return DB::transaction(function () use ($client, $email, $actor) {
            $user = User::query()->create([
                'company_id' => $client->company_id,
                'role_id' => Role::query()->forCompany($client->company_id)->where('name', DefaultRoles::CLIENT)->value('id'),
                'client_id' => $client->id,
                ...self::namesFrom($email),
                'email' => $email,
                'password' => null,
                'status' => UserStatus::Active,
            ]);
            ($this->recordActivity)('client.user_created', $user, $actor, null, ['client_id' => $client->id, 'email' => $email]);

            return $user;
        });
    }

    public function setActive(User $user, bool $active, User $actor): User
    {
        return DB::transaction(function () use ($user, $active, $actor) {
            $before = $user->status->value;
            $user->forceFill(['status' => $active ? UserStatus::Active : UserStatus::Inactive])->save();
            ($this->recordActivity)('client.user_updated', $user, $actor, ['status' => $before], ['status' => $user->status->value]);

            return $user;
        });
    }

    /** "juan.perez@x" → Juan / Perez; the portal only shows the email. @return array{first_name: string, last_name: string} */
    public static function namesFrom(string $email): array
    {
        $words = preg_split('/[._\-+]+/', Str::before($email, '@'), -1, PREG_SPLIT_NO_EMPTY) ?: [Str::before($email, '@')];

        return [
            'first_name' => Str::limit(Str::ucfirst($words[0]), 80, ''),
            'last_name' => Str::limit(Str::title(implode(' ', array_slice($words, 1))), 80, ''),
        ];
    }
}
