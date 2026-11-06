<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Domain\Notifications\Enums\NotificationEvent;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;

/**
 * RF-09: header bell (unread count, list, mark read) and e-mail preferences.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $page = $user->notifications()->latest()->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                ...$notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'unread_count' => $user->unreadNotifications()->count(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
            'message' => null,
            'errors' => null,
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return ApiResponse::ok(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::ok(['unread_count' => 0], 'Todas marcadas como leídas.');
    }

    public function preferences(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::ok(array_map(fn (NotificationEvent $event) => [
            'event' => $event->value,
            'label' => $event->label(),
            'email' => NotificationPreference::wantsEmail($user, $event),
            'locked' => $event->isMandatoryForAdmins() && $user->requiresTwoFactor(),
        ], NotificationEvent::cases()));
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.event' => ['required', Rule::enum(NotificationEvent::class)],
            'preferences.*.email' => ['required', 'boolean'],
        ]);
        $user = $request->user();

        foreach ($request->input('preferences') as $preference) {
            $event = NotificationEvent::from($preference['event']);
            if ($event->isMandatoryForAdmins() && $user->requiresTwoFactor()) {
                continue; // RF-09: Admins cannot mute hour-block e-mails.
            }
            NotificationPreference::query()->updateOrInsert(
                ['user_id' => $user->id, 'event' => $event->value],
                ['email' => (bool) $preference['email']],
            );
        }

        return $this->preferences($request);
    }
}
