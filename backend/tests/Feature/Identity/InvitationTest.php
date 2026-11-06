<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\InvitationNotification;
use App\Domain\Shared\Activity\ActivityLog;
use Illuminate\Support\Facades\Notification;

function invitationTokenFor(User $user): string
{
    $token = null;
    Notification::assertSentTo($user, InvitationNotification::class, function (InvitationNotification $notification) use (&$token, $user) {
        $token = basename($notification->toMail($user)->actionUrl);

        return true;
    });

    return $token;
}

it('lets an Admin invite a person who then sets their own password (RF-01.01)', function () {
    Notification::fake();
    $admin = adminUser();

    $this->actingAs($admin)->postJson('/api/v1/users', [
        'first_name' => 'Lu', 'last_name' => 'Díaz', 'email' => 'LU@example.com',
        'role_id' => roleNamed($admin, DefaultRoles::COLLABORATOR)->id,
    ])->assertCreated()->assertJsonPath('data.status', 'invited')->assertJsonPath('data.email', 'lu@example.com');

    $invitee = User::query()->where('email', 'lu@example.com')->firstOrFail();
    $token = invitationTokenFor($invitee);
    expect(ActivityLog::query()->where('event', 'user.invited')->exists())->toBeTrue();

    auth()->forgetGuards();
    $this->getJson("/api/v1/invitations/{$token}")->assertOk()->assertJsonPath('data.company', 'NexoSmart');
    $this->postJson("/api/v1/invitations/{$token}/accept", ['password' => 'Secreta123', 'password_confirmation' => 'Secreta123'])
        ->assertOk();

    expect($invitee->fresh()->status)->toBe(UserStatus::Active);
    $this->postJson("/api/v1/invitations/{$token}/accept", ['password' => 'Secreta123', 'password_confirmation' => 'Secreta123'])
        ->assertUnprocessable()->assertJsonPath('data.code', 'invitation_invalid');
});

it('rejects expired invitations', function () {
    Notification::fake();
    $admin = adminUser();
    $this->actingAs($admin)->postJson('/api/v1/users', [
        'first_name' => 'Lu', 'last_name' => 'Díaz', 'email' => 'lu@example.com',
        'role_id' => roleNamed($admin, DefaultRoles::COLLABORATOR)->id,
    ]);
    $token = invitationTokenFor(User::query()->where('email', 'lu@example.com')->firstOrFail());

    $this->travel(8)->days();

    $this->getJson("/api/v1/invitations/{$token}")->assertNotFound();
});

it('resending invalidates the previous link', function () {
    Notification::fake();
    $admin = adminUser();
    $invitee = User::factory()->invited()->forRole(roleNamed($admin, DefaultRoles::COLLABORATOR))->create();

    $this->actingAs($admin)->postJson("/api/v1/users/{$invitee->id}/invitation")->assertOk();
    $first = invitationTokenFor($invitee);
    Notification::fake();
    $this->actingAs($admin)->postJson("/api/v1/users/{$invitee->id}/invitation")->assertOk();

    $this->getJson("/api/v1/invitations/{$first}")->assertNotFound();
    $this->getJson('/api/v1/invitations/'.invitationTokenFor($invitee))->assertOk();
});

it('does not let a collaborator invite people', function () {
    $collaborator = userWithRole(adminUser());

    $this->actingAs($collaborator)->postJson('/api/v1/users', [
        'first_name' => 'X', 'last_name' => 'Y', 'email' => 'x@example.com', 'role_id' => $collaborator->role_id,
    ])->assertForbidden();
});
