<?php

use Illuminate\Support\Facades\Artisan;

it('renders one supervisord program per background process', function () {
    expect(Artisan::call('supervisor:sync', ['--dry-run' => true]))->toBe(0);

    expect(Artisan::output())
        ->toContain('[program:newdesk-queue]')
        ->toContain('[program:newdesk-scheduler]')
        ->toContain('[program:newdesk-reverb]')
        ->toContain('programs=newdesk-queue,newdesk-scheduler,newdesk-reverb');
});
