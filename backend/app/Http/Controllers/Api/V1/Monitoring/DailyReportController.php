<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Domain\Support\Actions\GenerateDailyReport;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * monitoreo /api/daily-report (D-067, D-068, D-074). Only commit messages come in: a GitHub token in
 * the body, or anything else, is refused.
 */
class DailyReportController extends Controller
{
    private const SHAPE = 'Mandá solo los repos y los mensajes de sus commits.';

    public function status(GenerateDailyReport $generate): JsonResponse
    {
        return ApiResponse::ok($generate->status());
    }

    public function store(Request $request, GenerateDailyReport $generate): JsonResponse
    {
        if (array_diff(array_keys($request->all()), ['repos']) !== []) {
            throw ValidationException::withMessages(['repos' => self::SHAPE]);
        }
        $repos = $request->validate([
            'repos' => ['present', 'array', 'list'],
            'repos.*' => ['array:repo,commits'],
            'repos.*.repo' => ['required', 'string'],
            'repos.*.commits' => ['nullable', 'array', 'list'],
            'repos.*.commits.*' => ['nullable', 'string'], // a blank message arrives as null (ConvertEmptyStringsToNull)
        ], ['repos' => self::SHAPE, 'repos.*' => self::SHAPE, 'repos.*.*' => self::SHAPE, 'repos.*.commits.*' => self::SHAPE])['repos'];

        return ApiResponse::ok($generate($request->user(), $repos));
    }
}
