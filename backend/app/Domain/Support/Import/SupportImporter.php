<?php

namespace App\Domain\Support\Import;

use App\Domain\Support\ReportImage;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Copies monitoreo's problem reports, their chat, images and the roadmaps (RF-13.12, ADR-0002 §9),
 * keeping the report, message and attachment ids (CA-13.23): the old links keep working. Runs after
 * StatusPageImporter, whose `import_mappings` translate clients, portal_projects and users. Reads
 * the legacy database and STORAGE_DIR and never writes them; a second run updates the same rows.
 */
final class SupportImporter
{
    private const SOURCE = 'monitoreo';

    /** Children first. */
    private const TABLES = ['support_roadmap_items', 'support_report_attachments', 'support_report_messages', 'support_reports'];

    private const REPORT = ['project_id', 'client_id', 'user_id', 'user_email', 'part', 'urgency', 'description', 'status', 'screenshot_path',
        'screenshot_type', 'screenshot_size', 'live_check', 'open_issues', 'recent_errors', 'browser', 'server_snapshot', 'ip', 'created_at',
        'updated_at', 'resolved_at', 'last_client_message_at', 'last_team_message_at', 'client_seen_at', 'team_seen_at'];

    /** @var list<string> files a legacy row names that were not in the storage directory */
    public array $missingFiles = [];

    private ConnectionInterface $legacy;

    private int $companyId;

    /** @param  string  $connection  the legacy database (`monitoreo_legacy`; tests pass their own) */
    public function __construct(private readonly string $connection = 'monitoreo_legacy') {}

    /** --fresh: the reports and everything hanging from them. */
    public function wipe(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->delete();
        }
    }

    /**
     * @param  string|null  $storageDir  monitoreo's STORAGE_DIR; null copies no file
     * @return array<string, int> rows copied per legacy table
     */
    public function import(int $companyId, ?string $storageDir): array
    {
        $this->legacy = DB::connection($this->connection);
        $this->companyId = $companyId;
        $this->missingFiles = [];
        $projects = $this->mapping('portal_projects');
        $clients = $this->mapping('clients');
        $users = $this->mapping('users');
        $user = fn (?int $legacyId) => $legacyId === null ? null : ($users[$legacyId] ?? null);

        $counts = [
            'portal_reports' => $this->copy('portal_reports', 'support_reports', ['id'], self::REPORT, fn (object $row) => [
                'project_id' => $projects[$row->project_id], 'client_id' => $clients[$row->client_id], 'user_id' => $user($row->user_id),
                'screenshot_size' => (int) $row->screenshot_size,
            ]),
            'portal_report_messages' => $this->copy('portal_report_messages', 'support_report_messages', ['id'],
                ['report_id', 'author_kind', 'user_id', 'author', 'body', 'internal', 'created_at'], fn (object $row) => ['user_id' => $user($row->user_id)]),
            'portal_report_attachments' => $this->copy('portal_report_attachments', 'support_report_attachments', ['id'],
                ['message_id', 'path', 'content_type', 'size', 'created_at']),
            // A roadmap of someone who is no longer on the team has no owner here.
            'report_roadmap' => $this->copy('report_roadmap', 'support_roadmap_items', ['user_id', 'report_id'], ['position', 'created_at'],
                fn (object $row) => ['user_id' => $user($row->user_id)], fn (object $row) => $user($row->user_id) !== null),
        ];
        if ($storageDir !== null) {
            $this->copyFiles(rtrim($storageDir, '/\\'));
        }

        return $counts;
    }

    /** @return array<int, int> legacy id → newDesk id */
    private function mapping(string $table): array
    {
        return DB::table('import_mappings')->where(['source' => self::SOURCE, 'source_table' => $table])->pluck('target_id', 'source_id')->all();
    }

    /**
     * A legacy table copied with its keys, in chunks; $override maps a row to its translated columns.
     *
     * @param  list<string>  $keys
     * @param  list<string>  $columns
     */
    private function copy(string $from, string $to, array $keys, array $columns, ?\Closure $override = null, ?\Closure $keep = null): int
    {
        $count = 0;
        $write = function ($rows) use ($to, $keys, $columns, $override, $keep, &$count) {
            $rows = $keep === null ? $rows : $rows->filter($keep);
            DB::table($to)->upsert(
                $rows->map(fn (object $row) => ['company_id' => $this->companyId, ...array_intersect_key((array) $row, array_flip([...$keys, ...$columns])),
                    ...($override ? $override($row) : [])])->values()->all(),
                $keys,
                ['company_id', ...$columns],
            );
            $count += $rows->count();
        };
        $query = $this->legacy->table($from);
        $keys === ['id'] ? $query->chunkById(500, $write) : $query->orderBy($keys[0])->orderBy($keys[1])->chunk(500, $write);

        return $count;
    }

    /** The screenshots and chat images, to the same relative paths on the private disk. */
    private function copyFiles(string $storageDir): void
    {
        $paths = $this->legacy->table('portal_reports')->whereNotNull('screenshot_path')->pluck('screenshot_path')
            ->merge($this->legacy->table('portal_report_attachments')->pluck('path'));
        foreach ($paths as $path) {
            $source = $storageDir.'/'.$path;
            if (str_contains($path, '..') || ! is_file($source)) {
                $this->missingFiles[] = $path;

                continue;
            }
            Storage::disk(ReportImage::DISK)->put($path, (string) file_get_contents($source));
        }
    }
}
