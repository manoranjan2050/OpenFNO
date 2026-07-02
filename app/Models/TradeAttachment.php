<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TradeAttachment extends Model
{
    protected $fillable = [
        'trade_id',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
