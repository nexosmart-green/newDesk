<?php

namespace App\Domain\Support\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\StatusPage\Actions\LiveCheckProject;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Queries\ProjectErrors;
use App\Domain\StatusPage\Queries\StatusPage;
use App\Domain\StatusPage\Status\ComponentState;
use App\Domain\Support\Events\ReportChanged;
use App\Domain\Support\Events\ReportSubmitted;
use App\Domain\Support\Models\Report;
use App\Domain\Support\Notifications\ReportSubmittedMail;
use App\Domain\Support\Queries\ServerSnapshotAtReport;
use App\Domain\Support\ReportImage;
use App\Domain\Support\UploadQuota;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * "Reportar un problema" (RF-13.08, monitoreo Reports.Submit, D-041): within the client's quotas,
 * stores the report with what the back gathers at that moment (a live check of every component,
 * the open issues, today's errors of the project's /api/errors, the client's browser and a snapshot
 * of its servers, D-050) and tells the team.
 */
final class SubmitReport
{
    /** Per client, counted on the stored reports so a restart never hands out a fresh budget (D-091, CA-13.17). */
    private const BUDGET = [3600 => 5, 86400 => 20];

    private const BUDGET_SPENT = 'Ya recibimos varios reportes en poco tiempo. Esperá un rato o escribinos por mail.';

    public function __construct(
        private readonly LiveCheckProject $liveCheck,
        private readonly ProjectErrors $errors,
        private readonly ServerSnapshotAtReport $serverSnapshot,
    ) {}

    /**
     * @param  array{part: string, urgency: string, description: string, browser: array<string, string>, ip: string}  $input  already validated
     * @return array{0: Report, 1: array<string, mixed>} the report and its live check
     */
    public function __invoke(Project $project, User $user, array $input, ?ReportImage $screenshot): array
    {
        if ($screenshot !== null) {
            UploadQuota::ensureRoom($project->client_id, $screenshot->size(), 'sin captura');
        }
        $now = CarbonImmutable::now()->utc();
        $this->ensureBudgetLeft($project->client_id, $now);

        // A failure in an extra only loses that part of the report (H14). The snapshot goes after the
        // live check, which has just asked the agents for fresh sections.
        $recent = $this->extra(fn () => $this->errors->recent($project));
        $path = $screenshot?->store($now);
        $live = ($this->liveCheck)($project);
        $snapshot = $this->extra(fn () => ($this->serverSnapshot)($project, $now));
        $issues = StatusPage::components($project)->filter(fn (Component $component) => ComponentState::isBad($component->status))
            ->map(fn (Component $component) => ['label' => $component->label, 'status' => $component->status,
                'since' => $component->status_since?->toJSON(), 'detail' => $component->detail])->values()->all();

        $report = Report::query()->create([
            'company_id' => $project->company_id, 'project_id' => $project->id, 'client_id' => $project->client_id,
            'user_id' => $user->id, 'user_email' => $user->email, 'part' => $input['part'] === '' ? null : $input['part'],
            'urgency' => $input['urgency'], 'description' => $input['description'], 'status' => Report::RECEIVED,
            'screenshot_path' => $path, 'screenshot_type' => $screenshot?->contentType, 'screenshot_size' => $screenshot?->size() ?? 0,
            'live_check' => $live, 'open_issues' => $issues, 'recent_errors' => $recent, 'browser' => $input['browser'],
            'server_snapshot' => $snapshot, 'ip' => mb_substr($input['ip'], 0, 64), 'created_at' => $now, 'updated_at' => $now,
        ])->setRelation('project', $project)->load('client');

        ReportSubmitted::dispatch($report, $live);
        ReportChanged::send($report, ReportChanged::REPORT);
        $this->mailTeam($report, $live);

        return [$report, $live];
    }

    /** monitoreo's ClientLive: the client sees its parts (Web, App, CMS), never the technical components. @param  array<string, mixed>  $live */
    public static function clientLive(array $live): array
    {
        return ['status' => $live['status'], 'message' => $live['message'], 'components' => $live['parts']];
    }

    /**
     * ponytail: counted on the rows, so two reports sent at the very same instant at the edge can both pass; a per-client lock if that matters.
     */
    private function ensureBudgetLeft(int $clientId, CarbonImmutable $now): void
    {
        foreach (self::BUDGET as $seconds => $limit) {
            $count = Report::query()->withoutGlobalScopes()->where('client_id', $clientId)
                ->where('created_at', '>=', $now->subSeconds($seconds)->format(Report::DATE_FORMAT))->count();
            if ($count >= $limit) {
                throw new BusinessRuleViolation(self::BUDGET_SPENT, 'report_budget', 429);
            }
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $gather
     * @return T|null
     */
    private function extra(callable $gather): mixed
    {
        try {
            return $gather();
        } catch (Throwable $e) {
            report($e); // a bug: logged with its stack in the errors channel (/bug-report)

            return null;
        }
    }

    /** @param  array<string, mixed>  $live */
    private function mailTeam(Report $report, array $live): void
    {
        $to = config('newdesk.monitoring.alert_email');
        if (! config('newdesk.monitoring.notify') || ! is_string($to) || $to === '') {
            return;
        }
        $url = rtrim((string) config('newdesk.frontend_url'), '/')."/monitoreo/reportes/{$report->id}";
        Mail::to($to)->queue(ReportSubmittedMail::for($report, $live, $url));
    }
}
