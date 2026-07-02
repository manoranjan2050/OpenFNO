<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Trade> */
class TradeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'underlying' => 'NIFTY',
            'strategy_name' => 'Iron Condor',
            'status' => 'open',
            'opened_at' => now()->subDay(),
            'notes' => $this->faker->sentence(),
            'tags' => ['test'],
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => 'closed',
            'closed_at' => now(),
            'realized_pnl' => 1000,
        ]);
    }
}
