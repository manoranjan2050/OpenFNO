<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instrument extends Model
{
    protected $fillable = [
        'exchange',
        'broker_token',
        'tradingsymbol',
        'underlying',
        'instrument_type',
        'expiry_date',
        'strike',
        'lot_size',
        'tick_size',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'strike' => 'decimal:2',
            'tick_size' => 'decimal:2',
        ];
    }

    public function tradeLegs(): HasMany
    {
        return $this->hasMany(TradeLeg::class);
    }
}
