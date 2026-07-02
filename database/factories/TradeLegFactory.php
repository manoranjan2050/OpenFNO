<?php

namespace Database\Factories;

use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\TradeLeg> */
class TradeLegFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trade_id' => Trade::factory(),
            'instrument_type' => 'CE',
            'expiry_date' => now()->addDays(7)->toDateString(),
            'strike' => 25000,
            'side' => 'SELL',
            'lots' => 1,
            'lot_size' => 75,
            'entry_price' => 100,
            'entry_at' => now()->subDay(),
        ];
    }
}
