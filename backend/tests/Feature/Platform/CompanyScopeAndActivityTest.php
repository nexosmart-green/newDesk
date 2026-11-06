<?php

use App\Domain\Identity\Models\Role;
use App\Domain\Shared\Activity\ActivityContext;
use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\Shared\Activity\ActivitySource;
use App\Domain\Shared\Activity\RecordActivity;

it('scopes company-owned models to the authenticated user company', function () {
    $nexo = provisionCompany('a@nexo.com');
    $other = provisionCompany('b@other.com');

    $this->actingAs($nexo);

    expect(Role::query()->pluck('company_id')->unique()->all())->toBe([$nexo->company_id])
        ->and(Role::query()->find(roleNamed($other, 'Admin')->id))->toBeNull();
});

it('records activity with the channel from the request context', function () {
    $admin = provisionCompany();
    $role = roleNamed($admin, 'Gestor');

    app(ActivityContext::class)->useMcp('laptop');
    $entry = app(RecordActivity::class)('role.updated', $role, $admin, ['name' => 'Gestor'], ['name' => 'PM']);

    expect($entry->source)->toBe(ActivitySource::Mcp)
        ->and($entry->token_name)->toBe('laptop')
        ->and($entry->company_id)->toBe($admin->company_id);
});

it('keeps the activity log append-only', function () {
    $admin = provisionCompany();
    $entry = app(RecordActivity::class)('role.updated', roleNamed($admin, 'Gestor'), $admin);

    expect(fn () => $entry->update(['event' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => ActivityLog::query()->findOrFail($entry->id)->delete())->toThrow(LogicException::class);
});
