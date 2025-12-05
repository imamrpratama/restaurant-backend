<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'table_id' => Table::factory(),
            'order_number' => 'ORD-' . strtoupper($this->faker->unique()->bothify('??? ###')),
            'status' => 'pending',
            'total_amount' => $this->faker->randomFloat(2, 10, 500),
        ];
    }
}
