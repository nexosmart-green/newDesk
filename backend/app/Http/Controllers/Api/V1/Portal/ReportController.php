<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Domain\StatusPage\Queries\StatusPage;
use App\Domain\Support\Actions\PostReportMessage;
use App\Domain\Support\Actions\SubmitReport;
use App\Domain\Support\Models\Report;
use App\Domain\Support\Models\ReportAttachment;
use App\Domain\Support\Models\ReportMessage;
use App\Domain\Support\ReportImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ReportMessageRequest;
use App\Http\Requests\Support\ReportRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * monitoreo /api/portal/projects/{slug}/reports (RF-13.08): a client reports a problem, follows its
 * reports and chats with the team about each one. A report of another client or of another project
 * does not exist for it (404); the team's author is "Equipo nexosmart" and internal notes never
 * show up (CA-13.18).
 */
class ReportController extends Controller
{
    private const LIST_LIMIT = 50;

    public function index(Request $request, string $slug): JsonResponse
    {
        $project = StatusPage::clientProject($request->user(), $slug);
        $reports = Report::query()->where('client_id', $request->user()->client_id)->where('project_id', $project->id)
            ->orderByDesc('created_at')->orderByDesc('id')->limit(self::LIST_LIMIT)->get();

        return ApiResponse::ok($reports->map(fn (Report $report) => $report->toClientPayload($slug))->all());
    }

    public function store(ReportRequest $request, string $slug, SubmitReport $submit): JsonResponse
    {
        [$report, $live] = $submit($request->project(), $request->user(), $request->report(), $request->screenshot());

        return ApiResponse::ok(['report' => $report->toClientPayload($slug), 'live' => SubmitReport::clientLive($live)], 'Recibimos tu reporte.', 201);
    }

    /** Opening it marks the team's answers as read for the client (D-049). */
    public function show(Request $request, string $slug, int $id): JsonResponse
    {
        $report = $this->report($request, $slug, $id);
        $messages = $report->chat(withInternal: false);
        $report->markSeen(ReportMessage::CLIENT);

        return ApiResponse::ok([
            'report' => $report->toClientPayload($slug),
            'messages' => $messages->map(fn (ReportMessage $message) => $message->toPayload(Report::clientPath($slug, $id), forClient: true))->all(),
        ]);
    }

    public function storeMessage(ReportMessageRequest $request, string $slug, int $id, PostReportMessage $post): JsonResponse
    {
        $report = $this->report($request, $slug, $id);
        $message = $post($report, $request->user(), $request->body(), $request->images(), internal: false);

        return ApiResponse::ok($message->toPayload(Report::clientPath($slug, $id), forClient: true), 'Mensaje enviado.', 201);
    }

    public function screenshot(Request $request, string $slug, int $id): Response
    {
        $report = $this->report($request, $slug, $id);

        return ReportImage::response($report->screenshot_path, $report->screenshot_type, 'El reporte no tiene captura.');
    }

    public function attachment(Request $request, string $slug, int $id, int $attachment): Response
    {
        $image = ReportAttachment::ofReport($this->report($request, $slug, $id), $attachment, withInternal: false);

        return ReportImage::response($image?->path, $image?->content_type, 'Imagen no encontrada.');
    }

    private function report(Request $request, string $slug, int $id): Report
    {
        $project = StatusPage::clientProject($request->user(), $slug);

        return Report::query()->where('client_id', $request->user()->client_id)->where('project_id', $project->id)
            ->findOrFail($id)->setRelation('project', $project);
    }
}
