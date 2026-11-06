<?php

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('sends a reset link without revealing whether the email exists', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

    Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
});

it('does not send reset links to inactive users', function () {
    Notification::fake();
    $user = User::factory()->inactive()->create();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token, 'email' => $user->email,
        'password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123',
    ])->assertOk();

    expect(Hash::check('NuevaClave123', $user->fresh()->password))->toBeTrue();

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token, 'email' => $user->email,
        'password' => 'OtraClave123', 'password_confirmation' => 'OtraClave123',
    ])->assertUnprocessable();
});
