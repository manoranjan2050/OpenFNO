<?php

namespace Database\Seeders;

use App\Models\Strategy;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Demo Trader',
            'email' => 'demo@openfno.local',
            'password' => bcrypt('password'),
        ]);

        $this->seedBuiltInStrategies();
        $this->seedDemoTrades($user);
    }

    private function seedBuiltInStrategies(): void
    {
        $templates = [
            [
                'name' => 'Iron Condor',
                'description' => 'Sell OTM call + put, buy further OTM wings. Defined-risk, range-bound.',
                'legs' => [
                    ['side' => 'SELL', 'type' => 'PE', 'strike_rule' => 'OTM-2'],
                    ['side' => 'BUY', 'type' => 'PE', 'strike_rule' => 'OTM-4'],
                    ['side' => 'SELL', 'type' => 'CE', 'strike_rule' => 'OTM+2'],
                    ['side' => 'BUY', 'type' => 'CE', 'strike_rule' => 'OTM+4'],
                ],
            ],
            [
                'name' => 'Short Straddle',
                'description' => 'Sell ATM call + put. Undefined risk, max theta.',
                'legs' => [
                    ['side' => 'SELL', 'type' => 'CE', 'strike_rule' => 'ATM'],
                    ['side' => 'SELL', 'type' => 'PE', 'strike_rule' => 'ATM'],
                ],
            ],
            [
                'name' => 'Short Strangle',
                'description' => 'Sell OTM call + put. Undefined risk, wider breakevens than straddle.',
                'legs' => [
                    ['side' => 'SELL', 'type' => 'CE', 'strike_rule' => 'OTM+2'],
                    ['side' => 'SELL', 'type' => 'PE', 'strike_rule' => 'OTM-2'],
                ],
            ],
            [
                'name' => 'Bull Call Spread',
                'description' => 'Buy ATM call, sell OTM call. Defined-risk directional.',
                'legs' => [
                    ['side' => 'BUY', 'type' => 'CE', 'strike_rule' => 'ATM'],
                    ['side' => 'SELL', 'type' => 'CE', 'strike_rule' => 'OTM+2'],
                ],
            ],
            [
                'name' => 'Bear Put Spread',
                'description' => 'Buy ATM put, sell OTM put. Defined-risk bearish.',
                'legs' => [
                    ['side' => 'BUY', 'type' => 'PE', 'strike_rule' => 'ATM'],
                    ['side' => 'SELL', 'type' => 'PE', 'strike_rule' => 'OTM-2'],
                ],
            ],
        ];

        foreach ($templates as $template) {
            Strategy::create([...$template, 'user_id' => null]);
        }
    }

    private function seedDemoTrades(User $user): void
    {
        // 1. Closed NIFTY Iron Condor — winner
        $trade = $user->trades()->create([
            'underlying' => 'NIFTY',
            'strategy_name' => 'Iron Condor',
            'status' => 'open',
            'opened_at' => now()->subDays(20)->setTime(9, 45),
            'notes' => "Weekly expiry IC. IV elevated after budget event, expecting range-bound move.\nAdjusted nothing; theta did the work.",
            'tags' => ['weekly', 'defined-risk'],
        ]);
        foreach ([
            ['CE', 25800, 'SELL', 92.50, 12.10],
            ['CE', 26000, 'BUY', 41.20, 4.85],
            ['PE', 25200, 'SELL', 88.75, 15.40],
            ['PE', 25000, 'BUY', 39.60, 6.20],
        ] as [$type, $strike, $side, $entry, $exit]) {
            $trade->legs()->create([
                'instrument_type' => $type,
                'expiry_date' => now()->subDays(14)->toDateString(),
                'strike' => $strike,
                'side' => $side,
                'lots' => 2,
                'lot_size' => 75,
                'entry_price' => $entry,
                'entry_at' => $trade->opened_at,
                'exit_price' => $exit,
                'exit_at' => now()->subDays(15)->setTime(14, 50),
            ]);
        }
        $trade->update([
            'status' => 'closed',
            'closed_at' => now()->subDays(15)->setTime(14, 50),
            'realized_pnl' => $trade->load('legs')->bookedPnl(),
        ]);

        // 2. Closed BANKNIFTY Short Straddle — loser (trend day)
        $trade = $user->trades()->create([
            'underlying' => 'BANKNIFTY',
            'strategy_name' => 'Short Straddle',
            'status' => 'open',
            'opened_at' => now()->subDays(10)->setTime(9, 20),
            'notes' => "Sold ATM straddle on expiry morning. RBI commentary triggered a trend day — stopped out.\nLesson: avoid naked straddles on event days.",
            'tags' => ['expiry-play', 'lesson'],
        ]);
        foreach ([
            ['CE', 57000, 'SELL', 210.00, 415.00],
            ['PE', 57000, 'SELL', 195.00, 62.00],
        ] as [$type, $strike, $side, $entry, $exit]) {
            $trade->legs()->create([
                'instrument_type' => $type,
                'expiry_date' => now()->subDays(10)->toDateString(),
                'strike' => $strike,
                'side' => $side,
                'lots' => 1,
                'lot_size' => 35,
                'entry_price' => $entry,
                'entry_at' => $trade->opened_at,
                'exit_price' => $exit,
                'exit_at' => now()->subDays(10)->setTime(11, 35),
            ]);
        }
        $trade->update([
            'status' => 'closed',
            'closed_at' => now()->subDays(10)->setTime(11, 35),
            'realized_pnl' => $trade->load('legs')->bookedPnl(),
        ]);

        // 3. Closed NIFTY futures directional — winner
        $trade = $user->trades()->create([
            'underlying' => 'NIFTY',
            'strategy_name' => 'Directional Future',
            'status' => 'open',
            'opened_at' => now()->subDays(6)->setTime(10, 15),
            'notes' => 'Breakout above previous week high with volume. Trailed to target.',
            'tags' => ['momentum'],
        ]);
        $trade->legs()->create([
            'instrument_type' => 'FUT',
            'expiry_date' => now()->addDays(22)->toDateString(),
            'strike' => null,
            'side' => 'BUY',
            'lots' => 1,
            'lot_size' => 75,
            'entry_price' => 25480.00,
            'entry_at' => $trade->opened_at,
            'exit_price' => 25642.50,
            'exit_at' => now()->subDays(4)->setTime(15, 10),
        ]);
        $trade->update([
            'status' => 'closed',
            'closed_at' => now()->subDays(4)->setTime(15, 10),
            'realized_pnl' => $trade->load('legs')->bookedPnl(),
        ]);

        // 4. Open NIFTY Bull Call Spread
        $trade = $user->trades()->create([
            'underlying' => 'NIFTY',
            'strategy_name' => 'Bull Call Spread',
            'status' => 'open',
            'opened_at' => now()->subDays(2)->setTime(9, 30),
            'notes' => 'Monthly expiry BCS into results season. Target 60% of max profit.',
            'tags' => ['monthly', 'defined-risk'],
        ]);
        foreach ([
            ['CE', 25600, 'BUY', 185.00],
            ['CE', 25900, 'SELL', 72.50],
        ] as [$type, $strike, $side, $entry]) {
            $trade->legs()->create([
                'instrument_type' => $type,
                'expiry_date' => now()->addDays(22)->toDateString(),
                'strike' => $strike,
                'side' => $side,
                'lots' => 2,
                'lot_size' => 75,
                'entry_price' => $entry,
                'entry_at' => $trade->opened_at,
            ]);
        }
    }
}
