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
        ->toContain("[program:newdesk-monitoring-worker]\nprocess_name=%(program_name)s_%(process_num)02d")
        ->toContain('queue:work redis --queue=monitoring --sleep=1')
        // Discord: a single process, in order, priority queue first (RF-13.09, D-107).
        ->toMatch('/\[program:newdesk-monitoring-discord\]\ncommand=\S+ \S+ queue:work redis --queue=discord-priority,discord .*?numprocs=1/s')
        ->toContain('programs=newdesk-queue,newdesk-scheduler,newdesk-reverb,newdesk-monitoring-poller,newdesk-monitoring-worker,newdesk-monitoring-discord');
});
