<?php

namespace Database\Factories;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'company_id' => fn (array $attributes) => Role::query()->withoutGlobalScopes()->findOrFail($attributes['role_id'])->company_id,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    /** @param  list<Permission>  $permissions */
    public function withPermissions(array $permissions): static
    {
        return $this->state(fn () => ['role_id' => Role::factory()->withPermissions($permissions)]);
    }

    public function forRole(Role $role): static
    {
        return $this->state(fn () => ['role_id' => $role->id, 'company_id' => $role->company_id]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Inactive]);
    }
}
