<?php

namespace App\Domain\StatusPage\Jobs;

use App\Domain\StatusPage\Actions\MeasurePageSpeed;
use App\Domain\StatusPage\Models\Component;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** "Medir ahora" (POST portal-admin/components/{id}/pagespeed): both strategies, ~30 s each, off the request. */
final class MeasureComponentPageSpeed implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public readonly int $componentId)
    {
        $this->onQueue('monitoring');
    }

    public function handle(MeasurePageSpeed $measure): void
    {
        $component = Component::query()->withoutGlobalScope('company')->find($this->componentId);
        if ($component !== null) {
            $measure->component($component);
        }
    }
}
