<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Contracts\ReportWriter;
use App\Domain\Assistant\Models\AssistantFile;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Support\ReportTable;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * RF-14.05: writes a report as a file on the private disk, owned by whoever asked and tied to the answer that
 * offers it. It expires in a week (AssistantFile::TTL_DAYS).
 */
final class GenerateAssistantReport
{
    public const DISK = 'local';

    public function __invoke(User $user, ReportTable $table, ReportWriter $writer, ?AssistantMessage $answer): AssistantFile
    {
        $uuid = (string) Str::uuid();
        $path = "assistant-reports/{$user->company_id}/{$uuid}.{$writer->format()}";
        $bytes = $writer->write($table);
        Storage::disk(self::DISK)->put($path, $bytes);

        return AssistantFile::query()->create([
            'uuid' => $uuid,
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'message_id' => $answer?->id,
            'name' => Str::slug($table->title).'-'.now()->setTimezone($user->effectiveTimezone())->format('Y-m-d').'.'.$writer->format(),
            'path' => $path,
            'format' => $writer->format(),
            'size' => strlen($bytes),
            'expires_at' => now()->addDays(AssistantFile::TTL_DAYS),
        ]);
    }
}
