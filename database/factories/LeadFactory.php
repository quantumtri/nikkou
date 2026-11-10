<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lead> */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'sales_id' => User::factory()->sales(),
            'source' => 'home_trial',
        ];
    }
}
