<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Domain\Monitoring\Queries\ListPage;
use App\Domain\Support\Actions\PostReportMessage;
use App\Domain\Support\Actions\SetReportStatus;
use App\Domain\Support\Models\Report;
use App\Domain\Support\Models\ReportAttachment;
use App\Domain\Support\Models\ReportMessage;
use App\Domain\Support\ReportImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ReportMessageRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * monitoreo /api/portal-admin/reports (RF-13.08): the team's queue of client reports, each with
 * everything gathered when it came in and its chat, internal notes included (D-049..D-051).
 */
class ReportController extends Controller
{
    private const STATUS_MESSAGE = 'Usá received, in_review o resolved.';

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(
            ['status' => ['nullable', Rule::in(Report::STATUSES)], 'page' => ['nullable', 'integer', 'min:1']],
            ['status' => self::STATUS_MESSAGE, 'page' => 'Página inválida.'],
        );
        $query = Report::query()->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status));
        $page = new ListPage((int) ($filters['page'] ?? 1), $query->count());
        $reports = $query->with(['client', 'project'])->orderByDesc('created_at')->orderByDesc('id')
            ->limit($page->perPage)->offset($page->offset())->get();

        return ApiResponse::page($reports->map(fn (Report $report) => $report->toRowPayload())->all(), $page->meta());
    }

    /** The menu's counter: new reports plus unread client messages. */
    public function attention(): JsonResponse
    {
        return ApiResponse::ok(['count' => Report::query()->needingTeam()->count()]);
    }

    /** Opening it marks the client's messages as read for the whole team (D-049). */
    public function show(Report $report): JsonResponse
    {
        $messages = $report->chat(withInternal: true);
        $report->markSeen(ReportMessage::TEAM);

        return ApiResponse::ok($report->load(['client', 'project'])->toDetailPayload($messages));
    }

    /** As monitoreo, the answer has no chat: the browser asks for the report again. */
    public function update(Request $request, Report $report, SetReportStatus $setStatus): JsonResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(Report::STATUSES)]], ['status' => self::STATUS_MESSAGE])['status'];
        $setStatus($report, $status, $request->user());

        return ApiResponse::ok($report->load(['client', 'project'])->toDetailPayload([]), 'Reporte actualizado.');
    }

    public function storeMessage(ReportMessageRequest $request, Report $report, PostReportMessage $post): JsonResponse
    {
        $message = $post($report, $request->user(), $request->body(), $request->images(), $request->internal());

        return ApiResponse::ok($message->toPayload(Report::teamPath($report->id), forClient: false), 'Mensaje enviado.', 201);
    }

    public function screenshot(Report $report): Response
    {
        return ReportImage::response($report->screenshot_path, $report->screenshot_type, 'El reporte no tiene captura.');
    }

    public function attachment(Report $report, int $attachment): Response
    {
        $image = ReportAttachment::ofReport($report, $attachment, withInternal: true);

        return ReportImage::response($image?->path, $image?->content_type, 'Imagen no encontrada.');
    }
}
