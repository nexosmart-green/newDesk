<?php

use App\Domain\People\Models\Area;
use App\Domain\People\Models\JobTitle;
use App\Domain\Shared\Activity\ActivityLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lets a person edit only their own contact, bio and language (RF-01.09)', function () {
    $collaborator = userWithRole(adminUser());

    $this->actingAs($collaborator)->putJson('/api/v1/me/profile', [
        'phone' => '+54 11 5555-0000', 'about' => 'Frontend', 'locale' => 'en', 'timezone' => 'Europe/Madrid',
        'job_title_id' => 999, 'first_name' => 'Hacker',
    ])->assertOk()
        ->assertJsonPath('data.phone', '+54 11 5555-0000')
        ->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.job_title', null)
        ->assertJsonPath('data.first_name', $collaborator->first_name);
});

it('lets the Admin edit the employment file, audited, with sensitive data hashed in the log', function () {
    $admin = adminUser();
    $person = userWithRole($admin);
    $jobTitle = JobTitle::query()->forCompany($admin->company_id)->where('name', 'Diseño')->firstOrFail();

    $this->actingAs($admin)->putJson("/api/v1/people/{$person->id}", [
        'first_name' => 'Juana', 'job_title_id' => $jobTitle->id, 'hired_at' => '2024-03-01',
        'cuil' => '20-12345678-9', 'reports_to_user_id' => $admin->id,
    ])->assertOk()
        ->assertJsonPath('data.first_name', 'Juana')
        ->assertJsonPath('data.job_title.name', 'Diseño')
        ->assertJsonPath('data.cuil', '20-12345678-9')
        ->assertJsonPath('data.reports_to.id', $admin->id);

    $audit = ActivityLog::query()->where('event', 'user.profile_updated')->firstOrFail();
    expect($audit->after['cuil'])->toBe(hash('sha256', '20-12345678-9'));
});

it('hides sensitive fields from colleagues (RNF-06)', function () {
    $admin = adminUser();
    $person = userWithRole($admin);
    $colleague = userWithRole($admin);
    $this->actingAs($admin)->putJson("/api/v1/people/{$person->id}", ['cuil' => '20123456789', 'address' => 'Calle 1']);

    $this->actingAs($colleague)->getJson("/api/v1/people/{$person->id}")
        ->assertOk()->assertJsonMissingPath('data.cuil')->assertJsonMissingPath('data.address');
    $this->actingAs($person)->getJson('/api/v1/me/profile')->assertJsonPath('data.address', 'Calle 1');
});

it('forbids collaborators from editing other files and hides other companies (404)', function () {
    $admin = adminUser('a@nexo.com');
    $collaborator = userWithRole($admin);
    $stranger = userWithRole(adminUser('b@other.com'));

    $this->actingAs($collaborator)->putJson("/api/v1/people/{$admin->id}", ['first_name' => 'X'])->assertForbidden();
    $this->actingAs($admin)->getJson("/api/v1/people/{$stranger->id}")->assertNotFound();
});

it('rejects reports-to loops', function () {
    $admin = adminUser();
    $lead = userWithRole($admin);
    $dev = userWithRole($admin);
    $this->actingAs($admin)->putJson("/api/v1/people/{$dev->id}", ['reports_to_user_id' => $lead->id])->assertOk();

    $this->actingAs($admin)->putJson("/api/v1/people/{$lead->id}", ['reports_to_user_id' => $dev->id])
        ->assertUnprocessable()->assertJsonPath('data.code', 'reports_to_cycle');
});

it('shows "mi equipo" (RF-01.07)', function () {
    $admin = adminUser();
    $lead = userWithRole($admin);
    $dev = userWithRole($admin);
    $this->actingAs($admin)->putJson("/api/v1/people/{$dev->id}", ['reports_to_user_id' => $lead->id]);

    $this->actingAs($lead)->getJson('/api/v1/me/team')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $dev->id);
});

it('stores a square avatar in 64 and 256 px (RF-01.05)', function () {
    Storage::fake('public');
    $person = userWithRole(adminUser());

    $avatar = $this->actingAs($person)->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg', 800, 600)])
        ->assertOk()->json('data.avatar');

    $directory = $person->fresh()->avatar_path;
    expect(getimagesizefromstring(Storage::disk('public')->get("{$directory}/avatar.webp"))[0])->toBe(64)
        ->and(getimagesizefromstring(Storage::disk('public')->get("{$directory}/profile.webp"))[1])->toBe(256)
        ->and($avatar['profile'])->toContain('profile.webp');
});

it('rejects unsupported avatar files and files over 5 MB', function () {
    $person = userWithRole(adminUser());

    $this->actingAs($person)->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()->assertJsonPath('data.code', 'avatar_format');
    $this->actingAs($person)->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('big.jpg')->size(6000)])
        ->assertUnprocessable();
});

it('filters the directory by area', function () {
    $admin = adminUser();
    $designer = userWithRole($admin);
    userWithRole($admin);
    $design = Area::query()->forCompany($admin->company_id)->where('name', 'Diseño')->firstOrFail();
    $this->actingAs($admin)->putJson("/api/v1/people/{$designer->id}", ['area_id' => $design->id]);

    $this->actingAs($designer)->getJson("/api/v1/people?area_id={$design->id}")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.area.name', 'Diseño');
});
