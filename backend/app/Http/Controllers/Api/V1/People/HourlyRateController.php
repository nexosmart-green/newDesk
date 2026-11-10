<?php

namespace App\Http\Controllers\Api\V1\People;

use App\Domain\Identity\Models\User;
use App\Domain\People\Actions\AddHourlyRate;
use App\Domain\People\Models\HourlyRate;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * RF-01.11 rate history. Admin only (CA-01.09).
 */
class HourlyRateController extends Controller
{
    public function index(User $user): JsonResponse
    {
        Gate::authorize('viewRates', $user);

        return ApiResponse::ok($user->hourlyRates()->orderByDesc('valid_from')->get()->map(fn (HourlyRate $rate) => [
            'id' => $rate->id,
            'amount_cents' => $rate->amount_cents,
            'currency' => $rate->currency,
            'valid_from' => $rate->valid_from->toDateString(),
            'valid_to' => $rate->valid_to?->toDateString(),
        ]));
    }

    public function store(Request $request, User $user, AddHourlyRate $addRate): JsonResponse
    {
        Gate::authorize('addRate', $user);

        $request->validate([
            'amount_cents' => ['required', 'integer', 'min:0', 'max:100000000'],
            'valid_from' => ['required', 'date_format:Y-m-d'],
        ]);

        $rate = $addRate($request->user(), $user, $request->integer('amount_cents'), Carbon::parse($request->string('valid_from')->value()));

        return ApiResponse::ok(['id' => $rate->id], 'Tarifa registrada.', 201);
    }
}
