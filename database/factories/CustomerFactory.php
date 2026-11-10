<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'whatsapp_number' => '08'.fake()->numerify('##########'),
            'address' => fake()->streetAddress(),
            'city' => 'Surabaya',
            'district' => fake()->randomElement(['Rungkut', 'Gubeng', 'Wonokromo', 'Sukolilo', 'Tegalsari']),
            'data_consent_at' => now(),
            'whatsapp_consent_at' => now(),
        ];
    }
}
