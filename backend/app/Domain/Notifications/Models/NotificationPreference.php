<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Enums\NotificationEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property NotificationEvent $event
 * @property bool $email
 */
class NotificationPreference extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['user_id', 'event', 'email'];

    protected function casts(): array
    {
        return ['event' => NotificationEvent::class, 'email' => 'boolean'];
    }

    public static function wantsEmail(User $user, NotificationEvent $event): bool
    {
        if ($event->isMandatoryForAdmins() && $user->requiresTwoFactor()) {
            return true;
        }

        $preference = self::query()->where('user_id', $user->id)->where('event', $event)->value('email');

        return $preference === null ? true : (bool) $preference;
    }
}
