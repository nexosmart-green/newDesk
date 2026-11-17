<?php

namespace App\Domain\Support\Events;

use App\Domain\Support\Models\Report;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A client sent a problem report (RF-13.08). The team's notices listen here: the mail goes out in
 * SubmitReport while `newdesk.monitoring.notify` is on, and the project's Discord channel (RF-13.09,
 * D-054, D-059) is a listener of this event. Not broadcast: the browsers hear ReportChanged.
 */
final class ReportSubmitted
{
    use Dispatchable;
    use SerializesModels; // a queued listener (Discord) gets the report by id

    /** @param  array<string, mixed>  $live  the live check as stored on the report (LiveCheckProject) */
    public function __construct(public readonly Report $report, public readonly array $live) {}
}
