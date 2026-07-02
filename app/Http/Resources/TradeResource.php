<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Trade */
class TradeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'underlying' => $this->underlying,
            'strategy_id' => $this->strategy_id,
            'strategy_name' => $this->strategy_name,
            'status' => $this->status,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'realized_pnl' => $this->realized_pnl,
            'booked_pnl' => $this->whenLoaded('legs', fn () => $this->bookedPnl()),
            'notes' => $this->notes,
            'tags' => $this->tags ?? [],
            'legs' => TradeLegResource::collection($this->whenLoaded('legs')),
            'attachments' => TradeAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
