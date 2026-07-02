<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TradeLeg */
class TradeLegResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instrument_id' => $this->instrument_id,
            'tradingsymbol' => $this->tradingsymbol,
            'instrument_type' => $this->instrument_type,
            'expiry_date' => $this->expiry_date?->toDateString(),
            'strike' => $this->strike,
            'side' => $this->side,
            'lots' => $this->lots,
            'lot_size' => $this->lot_size,
            'quantity' => $this->quantity(),
            'entry_price' => $this->entry_price,
            'entry_at' => $this->entry_at?->toIso8601String(),
            'exit_price' => $this->exit_price,
            'exit_at' => $this->exit_at?->toIso8601String(),
            'pnl' => $this->pnl(),
            'is_open' => $this->exit_price === null,
        ];
    }
}
