<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trade extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'strategy_id',
        'underlying',
        'strategy_name',
        'status',
        'opened_at',
        'closed_at',
        'realized_pnl',
        'notes',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'realized_pnl' => 'decimal:2',
            'tags' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }

    public function legs(): HasMany
    {
        return $this->hasMany(TradeLeg::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TradeAttachment::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * P&L booked so far: sum over legs that have an exit price.
     * Live/unrealized P&L for open legs needs market prices (Phase 5).
     */
    public function bookedPnl(): float
    {
        return $this->legs
            ->filter(fn (TradeLeg $leg) => $leg->exit_price !== null)
            ->sum(fn (TradeLeg $leg) => $leg->pnl());
    }

    /** True when every leg has been exited. */
    public function allLegsClosed(): bool
    {
        return $this->legs->every(fn (TradeLeg $leg) => $leg->exit_price !== null);
    }
}
