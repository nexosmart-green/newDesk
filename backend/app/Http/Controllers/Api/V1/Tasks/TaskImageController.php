<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Actions\StoreTaskImage;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskImage;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Images inside task descriptions (markdown). Upload = may create tasks in the project; read = may see it.
 */
class TaskImageController extends Controller
{
    private const MAX_KILOBYTES = 5120;

    public function store(Request $request, Project $project, StoreTaskImage $storeImage): JsonResponse
    {
        Gate::authorize('create', [Task::class, $project]);
        $request->validate(
            ['image' => ['required', 'file', 'max:'.self::MAX_KILOBYTES]],
            [
                'image.required' => 'Elegí una imagen para subir.',
                'image.file' => 'Elegí una imagen para subir.',
                'image.uploaded' => 'La imagen no se pudo subir: puede pesar hasta 5 MB.',
                'image.max' => 'La imagen puede pesar hasta 5 MB.',
            ],
        );

        $image = $storeImage($request->user(), $project, $request->file('image'));

        return ApiResponse::ok(['url' => $image->url()], 'Imagen subida.', 201);
    }

    public function show(TaskImage $taskImage): StreamedResponse
    {
        Gate::authorize('view', $taskImage);
        abort_unless(Storage::disk(TaskImage::DISK)->exists($taskImage->path), 404);

        return Storage::disk(TaskImage::DISK)->response($taskImage->path, null, [
            'Content-Type' => $taskImage->mime,
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
