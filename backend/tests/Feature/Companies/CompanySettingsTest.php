<?php

use App\Domain\Shared\Activity\ActivityLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('exposes the company with RF-11 defaults', function () {
    $this->actingAs(userWithRole(adminUser()))->getJson('/api/v1/company')
        ->assertOk()
        ->assertJsonPath('data.currency', 'ARS')
        ->assertJsonPath('data.timezone', 'America/Argentina/Buenos_Aires')
        ->assertJsonPath('data.settings.manual_entry_days_back', 7)
        ->assertJsonPath('data.settings.forgotten_timer_max_hours', 10)
        ->assertJsonPath('data.settings.hour_alert_thresholds', [80, 100]);
});

it('lets the Admin change rules, auditing the change', function () {
    $admin = adminUser();

    $this->actingAs($admin)->putJson('/api/v1/company', [
        'name' => 'NexoSmart SRL', 'currency' => 'EUR', 'timezone' => 'Europe/Madrid',
        'settings' => ['manual_entry_days_back' => 3],
    ])->assertOk()
        ->assertJsonPath('data.settings.manual_entry_days_back', 3)
        ->assertJsonPath('data.settings.forgotten_timer_max_hours', 10);

    expect(ActivityLog::query()->where('event', 'company.updated')->first()->before['name'])->toBe('NexoSmart');
});

it('rejects invalid configuration', function () {
    $this->actingAs(adminUser())->putJson('/api/v1/company', [
        'name' => 'Default Company', 'currency' => 'ars', 'timezone' => 'Mars/Base',
        'settings' => ['hour_alert_thresholds' => [90, 80], 'unknown' => 1],
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'name', 'currency', 'timezone', 'settings', 'settings.hour_alert_thresholds.1',
    ]);
});

it('uploads a logo and refuses SVG', function () {
    Storage::fake('public');
    $admin = adminUser();

    $this->actingAs($admin)->postJson('/api/v1/company/logo', ['logo' => UploadedFile::fake()->image('logo.png')])
        ->assertOk()->assertJsonPath('data.logo_url', fn ($url) => str_contains($url, 'companies/'));
    $this->actingAs($admin)->postJson('/api/v1/company/logo', ['logo' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml')])
        ->assertUnprocessable();
});

it('forbids changing the company without company.manage', function () {
    $this->actingAs(userWithRole(adminUser()))->putJson('/api/v1/company', [
        'name' => 'X', 'currency' => 'ARS', 'timezone' => 'UTC',
    ])->assertForbidden();
});
