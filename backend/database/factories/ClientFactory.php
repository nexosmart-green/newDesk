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
            'legal_name' => fake()->company().' S.A.',
            'trade_name' => fake()->company(),
            'tax_id' => '30-'.fake()->numerify('########').'-'.fake()->randomDigit(),
            'email' => fake()->companyEmail(),
            'status' => 'active',
        ];
    }
}
