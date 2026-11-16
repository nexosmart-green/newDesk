<?php

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Domain\Assistant\Actions\GenerateAssistantReport;
use App\Domain\Assistant\Models\AssistantFile;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** RF-13.05: download of a report the assistant generated (only its owner, while it has not expired). */
class AssistantFileController extends Controller
{
    private const MIME = [
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pdf' => 'application/pdf',
    ];

    public function download(AssistantFile $file): StreamedResponse
    {
        Gate::authorize('download', $file);
        abort_unless(Storage::disk(GenerateAssistantReport::DISK)->exists($file->path), 404);

        return Storage::disk(GenerateAssistantReport::DISK)->download($file->path, $file->name, [
            'Content-Type' => self::MIME[$file->format] ?? 'application/octet-stream',
            'Cache-Control' => 'private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
