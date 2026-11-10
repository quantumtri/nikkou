<?php

namespace Database\Factories;

use App\Models\UnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitType> */
class UnitTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'H2 '.fake()->unique()->lexify('????'),
            'description' => null,
            'default_components' => ['Unit utama', 'Adaptor dan kabel daya', 'Selang', 'Kanula', 'Buku panduan'],
        ];
    }
}
