<?php

use Illuminate\Support\Facades\Artisan;

it('renders one supervisord program per background process', function () {
    expect(Artisan::call('supervisor:sync', ['--dry-run' => true]))->toBe(0);

    expect(Artisan::output())
        ->toContain('[program:newdesk-queue]')
        ->toContain('[program:newdesk-scheduler]')
        ->toContain('[program:newdesk-reverb]')
        ->toContain('[program:newdesk-monitoring-poller]')
        ->toContain('artisan monitoring:poll')
        ->toContain('[program:newdesk-monitoring-worker]')
        ->toContain('queue:work redis --queue=monitoring,discord')
        ->toContain('programs=newdesk-queue,newdesk-scheduler,newdesk-reverb,newdesk-monitoring-poller,newdesk-monitoring-worker');
});
