<?php

namespace Database\Factories;

use App\Enums\UnitStatus;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Unit> */
class UnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'NK-'.fake()->unique()->numerify('####'),
            'unit_type_id' => UnitType::factory(),
            'received_on' => today()->subDays(fake()->numberBetween(1, 60)),
            'created_by_id' => User::factory()->admin(),
            'forwarder' => null,
            'location' => 'Gudang Surabaya',
        ];
    }

    /** Test-only shortcut; application code must go through UnitStatusService. */
    public function status(UnitStatus $status): static
    {
        return $this->afterMaking(fn (Unit $unit) => $unit->status = $status);
    }
}
