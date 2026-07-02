<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeLeg extends Model
{
    use HasFactory;

    protected $fillable = [
        'trade_id',
        'instrument_id',
        'tradingsymbol',
        'instrument_type',
        'expiry_date',
        'strike',
        'side',
        'lots',
        'lot_size',
        'entry_price',
        'entry_at',
        'exit_price',
        'exit_at',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'strike' => 'decimal:2',
            'entry_price' => 'decimal:2',
            'exit_price' => 'decimal:2',
            'entry_at' => 'datetime',
            'exit_at' => 'datetime',
        ];
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function quantity(): int
    {
        return $this->lots * $this->lot_size;
    }

    /**
     * Realized P&L for this leg. Null while the leg is open.
     * lot_size is the value snapshotted at entry, deliberately not the
     * instrument's current lot size.
     */
    public function pnl(): ?float
    {
        if ($this->exit_price === null) {
            return null;
        }

        $direction = $this->side === 'BUY' ? 1 : -1;

        return ((float) $this->exit_price - (float) $this->entry_price)
            * $direction
            * $this->quantity();
    }
}
