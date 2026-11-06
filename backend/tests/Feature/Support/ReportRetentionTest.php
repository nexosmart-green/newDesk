<?php

use App\Domain\Support\Actions\PurgeReports;
use App\Domain\Support\Models\Report;
use App\Domain\Support\Models\ReportMessage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ReportFixtures as Reports;
use Tests\Support\StatusPageFixtures as Fixtures;

/** Port of monitoreo's reports_purge_test.go and the report rows of store.Purge (D-041, D-049, D-093). */
beforeEach(function () {
    $this->disk = Storage::fake('local');
    $this->admin = provisionCompany();
    $this->project = Fixtures::project($this->admin);
});

/** A file under the private disk, last written $age seconds ago. */
function storedFile(string $path, int $age): void
{
    Storage::disk('local')->put($path, 'img');
    touch(Storage::disk('local')->path($path), time() - $age);
}

// portal/reports_purge_test.go TestPurgeRemovesOrphanFiles (H15)
it('removes the files under reports/ that no row names, once they are an hour old', function () {
    foreach (['reports/2026/10/aaaa.png', 'reports/2026/10/bbbb.png', 'reports/2026/09/cccc.png'] as $path) {
        storedFile($path, 7200);
    }
    storedFile('reports/2026/10/dddd.png', 60); // just written, its row is on its way
    storedFile('errors/2026-10-01.log', 172800);
    $report = Reports::stored($this->project, ['screenshot_path' => 'reports/2026/10/aaaa.png', 'screenshot_type' => 'image/png', 'screenshot_size' => 3]);
    $message = ReportMessage::query()->create(['company_id' => $report->company_id, 'report_id' => $report->id, 'author_kind' => 'client',
        'author' => 'dueno@playinn.com', 'body' => 'otra', 'created_at' => now()]);
    $message->attachments()->create(['company_id' => $report->company_id, 'path' => 'reports/2026/10/bbbb.png', 'content_type' => 'image/png', 'size' => 3, 'created_at' => now()]);

    expect(app(PurgeReports::class)(now()))->toBe(['screenshots' => 0, 'attachments' => 0, 'reports' => 0, 'orphan_files' => 1]);
    $this->disk->assertMissing('reports/2026/09/cccc.png');
    foreach (['reports/2026/10/aaaa.png', 'reports/2026/10/bbbb.png', 'reports/2026/10/dddd.png', 'errors/2026-10-01.log'] as $path) {
        $this->disk->assertExists($path);
    }
});

it('purges report images after 180 days and the reports themselves after a year (D-041)', function () {
    storedFile('reports/2026/01/old.png', 7200);
    $old = Reports::stored($this->project, ['created_at' => now()->subDays(181), 'screenshot_path' => 'reports/2026/01/old.png',
        'screenshot_type' => 'image/png', 'screenshot_size' => 3]);
    $expired = Reports::stored($this->project, ['created_at' => now()->subYear()->subDay()]);
    ReportMessage::query()->create(['company_id' => $expired->company_id, 'report_id' => $expired->id, 'author_kind' => 'client',
        'author' => 'x@y.z', 'body' => 'hola', 'created_at' => now()->subYear()->subDay()]);
    $recent = Reports::stored($this->project);

    Artisan::call('support:retention');

    expect(Artisan::output())->toContain('screenshots: 1 borrados')->toContain('reports: 1 borrados')
        ->and($old->fresh()->only(['screenshot_path', 'screenshot_type', 'screenshot_size']))
        ->toBe(['screenshot_path' => null, 'screenshot_type' => null, 'screenshot_size' => 3])
        ->and(Report::query()->pluck('id')->sort()->values()->all())->toBe([$old->id, $recent->id])
        ->and(ReportMessage::query()->count())->toBe(0);
    $this->disk->assertMissing('reports/2026/01/old.png');
});
