<?php

namespace Database\Factories;

use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Role> */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->jobTitle(),
            'is_system' => false,
        ];
    }

    /** @param  list<Permission>  $permissions */
    public function withPermissions(array $permissions): static
    {
        return $this->afterCreating(fn (Role $role) => $role->syncPermissions($permissions));
    }
}
