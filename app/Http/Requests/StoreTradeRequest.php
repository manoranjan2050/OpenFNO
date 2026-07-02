<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'underlying' => ['required', 'string', 'max:30'],
            'strategy_id' => ['nullable', 'integer', 'exists:strategies,id'],
            'strategy_name' => ['nullable', 'string', 'max:100'],
            'opened_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:65000'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],

            'legs' => ['required', 'array', 'min:1'],
            'legs.*.instrument_type' => ['required', 'in:FUT,CE,PE'],
            'legs.*.expiry_date' => ['required', 'date'],
            'legs.*.strike' => ['required_unless:legs.*.instrument_type,FUT', 'nullable', 'numeric', 'min:0'],
            'legs.*.side' => ['required', 'in:BUY,SELL'],
            'legs.*.lots' => ['required', 'integer', 'min:1', 'max:10000'],
            'legs.*.lot_size' => ['required', 'integer', 'min:1', 'max:100000'],
            'legs.*.entry_price' => ['required', 'numeric', 'min:0'],
            'legs.*.entry_at' => ['nullable', 'date'],
            'legs.*.tradingsymbol' => ['nullable', 'string', 'max:60'],
            'legs.*.instrument_id' => ['nullable', 'integer', 'exists:instruments,id'],
        ];
    }
}
