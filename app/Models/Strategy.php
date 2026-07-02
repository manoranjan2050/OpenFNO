<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Strategy extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'legs',
    ];

    protected function casts(): array
    {
        return [
            'legs' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isBuiltIn(): bool
    {
        return $this->user_id === null;
    }
}
