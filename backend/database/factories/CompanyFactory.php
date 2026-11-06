<?php

namespace Database\Factories;

use App\Domain\Companies\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'currency' => 'ARS',
            'timezone' => 'America/Argentina/Buenos_Aires',
        ];
    }
}
