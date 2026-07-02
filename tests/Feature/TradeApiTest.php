<?php

namespace Tests\Feature;

use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TradeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_guest_cannot_access_trades(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/trades')->assertUnauthorized();
    }

    public function test_user_can_create_multi_leg_trade(): void
    {
        $response = $this->postJson('/api/v1/trades', [
            'underlying' => 'NIFTY',
            'strategy_name' => 'Short Strangle',
            'opened_at' => now()->toIso8601String(),
            'tags' => ['weekly'],
            'legs' => [
                ['instrument_type' => 'CE', 'expiry_date' => now()->addDays(7)->toDateString(), 'strike' => 25800, 'side' => 'SELL', 'lots' => 1, 'lot_size' => 75, 'entry_price' => 90],
                ['instrument_type' => 'PE', 'expiry_date' => now()->addDays(7)->toDateString(), 'strike' => 25200, 'side' => 'SELL', 'lots' => 1, 'lot_size' => 75, 'entry_price' => 85],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonCount(2, 'data.legs');

        $this->assertDatabaseCount('trade_legs', 2);
    }

    public function test_option_leg_requires_strike_but_future_leg_does_not(): void
    {
        $base = [
            'underlying' => 'NIFTY',
            'opened_at' => now()->toIso8601String(),
        ];
        $leg = ['expiry_date' => now()->addDays(7)->toDateString(), 'side' => 'BUY', 'lots' => 1, 'lot_size' => 75, 'entry_price' => 100];

        $this->postJson('/api/v1/trades', [
            ...$base,
            'legs' => [[...$leg, 'instrument_type' => 'CE', 'strike' => null]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['legs.0.strike']);

        $this->postJson('/api/v1/trades', [
            ...$base,
            'legs' => [[...$leg, 'instrument_type' => 'FUT', 'strike' => null]],
        ])->assertCreated();
    }

    public function test_user_cannot_see_another_users_trade(): void
    {
        $foreign = Trade::factory()->create(); // belongs to a different user

        $this->getJson("/api/v1/trades/{$foreign->id}")->assertForbidden();
        $this->getJson('/api/v1/trades')->assertJsonCount(0, 'data');
    }

    public function test_closing_a_trade_freezes_realized_pnl(): void
    {
        $trade = Trade::factory()->for($this->user)->create();
        // SELL 100 → exit 40 on 75 qty = +4500; BUY 50 → exit 80 on 75 qty = +2250
        $sell = $trade->legs()->create([
            'instrument_type' => 'CE', 'expiry_date' => now()->addDays(7)->toDateString(),
            'strike' => 25000, 'side' => 'SELL', 'lots' => 1, 'lot_size' => 75,
            'entry_price' => 100, 'entry_at' => now()->subDay(),
        ]);
        $buy = $trade->legs()->create([
            'instrument_type' => 'PE', 'expiry_date' => now()->addDays(7)->toDateString(),
            'strike' => 25000, 'side' => 'BUY', 'lots' => 1, 'lot_size' => 75,
            'entry_price' => 50, 'entry_at' => now()->subDay(),
        ]);

        $response = $this->postJson("/api/v1/trades/{$trade->id}/close", [
            'legs' => [
                ['id' => $sell->id, 'exit_price' => 40],
                ['id' => $buy->id, 'exit_price' => 80],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.realized_pnl', '6750.00');
    }

    public function test_closing_requires_exit_price_for_every_open_leg(): void
    {
        $trade = Trade::factory()->for($this->user)->create();
        $leg = $trade->legs()->create([
            'instrument_type' => 'CE', 'expiry_date' => now()->addDays(7)->toDateString(),
            'strike' => 25000, 'side' => 'SELL', 'lots' => 1, 'lot_size' => 75,
            'entry_price' => 100, 'entry_at' => now()->subDay(),
        ]);
        $trade->legs()->create([
            'instrument_type' => 'PE', 'expiry_date' => now()->addDays(7)->toDateString(),
            'strike' => 25000, 'side' => 'SELL', 'lots' => 1, 'lot_size' => 75,
            'entry_price' => 90, 'entry_at' => now()->subDay(),
        ]);

        $this->postJson("/api/v1/trades/{$trade->id}/close", [
            'legs' => [['id' => $leg->id, 'exit_price' => 40]],
        ])->assertUnprocessable();

        $this->assertSame('open', $trade->fresh()->status);
    }

    public function test_legs_of_a_closed_trade_cannot_be_replaced(): void
    {
        $trade = Trade::factory()->for($this->user)->closed()->create();

        $this->putJson("/api/v1/trades/{$trade->id}", [
            'legs' => [
                ['instrument_type' => 'CE', 'expiry_date' => now()->addDays(7)->toDateString(), 'strike' => 25000, 'side' => 'SELL', 'lots' => 1, 'lot_size' => 75, 'entry_price' => 100],
            ],
        ])->assertUnprocessable();
    }

    public function test_stats_reflect_closed_trades_only(): void
    {
        Trade::factory()->for($this->user)->closed()->create(['realized_pnl' => 5000]);
        Trade::factory()->for($this->user)->closed()->create(['realized_pnl' => -2000]);
        Trade::factory()->for($this->user)->create(); // open — excluded from P&L

        $this->getJson('/api/v1/stats')
            ->assertOk()
            ->assertJsonPath('open_trades', 1)
            ->assertJsonPath('closed_trades', 2)
            ->assertJsonPath('total_pnl', 3000)
            ->assertJsonPath('win_rate', 50);
    }

    public function test_deleted_trade_is_hidden_from_listing_and_stats(): void
    {
        $trade = Trade::factory()->for($this->user)->closed()->create(['realized_pnl' => 5000]);

        $this->deleteJson("/api/v1/trades/{$trade->id}")->assertNoContent();

        $this->getJson('/api/v1/trades')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/stats')->assertJsonPath('total_pnl', 0);
    }
}
