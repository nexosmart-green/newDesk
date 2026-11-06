<?php

namespace Database\Factories;

use App\Domain\Clients\Models\Client;
use App\Domain\Companies\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->company(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
