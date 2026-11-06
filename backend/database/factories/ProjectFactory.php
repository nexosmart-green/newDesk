<?php

namespace Database\Factories;

use App\Domain\Clients\Models\Client;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'company_id' => fn (array $attributes) => Client::query()->withoutGlobalScopes()->findOrFail($attributes['client_id'])->company_id,
            'name' => fake()->unique()->catchPhrase(),
            'code_prefix' => fake()->unique()->lexify('???????'),
            'slug' => fn (array $attributes) => $attributes['code_prefix'],
            'status' => ProjectStatus::Active,
        ];
    }
}
