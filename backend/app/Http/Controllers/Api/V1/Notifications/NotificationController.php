<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Domain\Notifications\Actions\UpdateNotificationPreferences;
use App\Domain\Notifications\Queries\NotificationPreferenceList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\UpdateNotificationPreferencesRequest;
use App\Http\Resources\NotificationResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * RF-09: header bell (unread count, list, mark read) and e-mail preferences.
 */
class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        return NotificationResource::collection($user->notifications()->latest()->paginate(self::PER_PAGE))
            ->additional(['meta' => ['unread_count' => $user->unreadNotifications()->count()]]);
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

    public function preferences(Request $request, NotificationPreferenceList $preferences): JsonResponse
    {
        return ApiResponse::ok($preferences($request->user()));
    }

    public function updatePreferences(
        UpdateNotificationPreferencesRequest $request,
        UpdateNotificationPreferences $update,
        NotificationPreferenceList $preferences,
    ): JsonResponse {
        $update($request->user(), $request->preferences());

        return ApiResponse::ok($preferences($request->user()));
    }
}
