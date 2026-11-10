<?php

namespace Database\Factories;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Loan> */
class LoanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'customer_id' => Customer::factory(),
            'sales_id' => User::factory()->sales(),
            'type' => LoanType::HomeTrial,
            'starts_on' => today(),
            'due_on' => today()->addDays(3),
            'status' => LoanStatus::Active,
        ];
    }

    public function overdue(): static
    {
        return $this->state(['starts_on' => today()->subDays(5), 'due_on' => today()->subDay()]);
    }

    public function returned(): static
    {
        return $this->state(['status' => LoanStatus::Returned, 'returned_at' => now()]);
    }
}
