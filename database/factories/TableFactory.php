<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'table_number' => 'T-' . $this->faker->unique()->numberBetween(1, 50),
            'capacity' => $this->faker->numberBetween(2, 8),
            'status' => 'available',
        ];
    }
}
