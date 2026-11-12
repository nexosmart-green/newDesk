<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Tasks\Actions\DeleteTaskAttachment;
use App\Domain\Tasks\Actions\StoreTaskAttachment;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskAttachmentRequest;
use App\Http\Resources\TaskAttachmentResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RF-04.09: files attached to a task. Seeing the task = seeing and adding its files.
 */
class TaskAttachmentController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        $attachments = $task->attachments()->with('uploader')->latest('id')->get();

        return ApiResponse::ok(TaskAttachmentResource::collection($attachments));
    }

    public function store(StoreTaskAttachmentRequest $request, Task $task, StoreTaskAttachment $store): JsonResponse
    {
        $attachment = $store($request->user(), $task, $request->file('file'));

        return ApiResponse::ok(TaskAttachmentResource::make($attachment->load('uploader')), "Adjuntaste {$attachment->original_name}.", 201);
    }

    /**
     * Served under the API origin, so the headers keep a malicious file inert: the browser may not guess
     * another type (nosniff) and anything it renders runs sandboxed with no resources (CSP).
     * PDFs go without the CSP: Chrome refuses to open its PDF viewer under `sandbox`. Their content was
     * sniffed on upload (an HTML renamed .pdf is rejected) and nosniff keeps the browser on application/pdf.
     */
    public function show(TaskAttachment $taskAttachment): StreamedResponse
    {
        Gate::authorize('view', $taskAttachment);
        abort_unless(Storage::disk(TaskAttachment::DISK)->exists($taskAttachment->path), 404);

        $headers = [
            'Content-Type' => $taskAttachment->mime,
            'Cache-Control' => 'private',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if ($taskAttachment->mime !== 'application/pdf') {
            $headers['Content-Security-Policy'] = "sandbox; default-src 'none'";
        }

        return Storage::disk(TaskAttachment::DISK)->response(
            $taskAttachment->path,
            $taskAttachment->original_name,
            $headers,
            $taskAttachment->opensInline() ? 'inline' : 'attachment',
        );
    }

    public function destroy(Request $request, TaskAttachment $taskAttachment, DeleteTaskAttachment $delete): JsonResponse
    {
        Gate::authorize('delete', $taskAttachment);

        $delete($request->user(), $taskAttachment);

        return ApiResponse::ok(message: "Borraste {$taskAttachment->original_name}.");
    }
}
