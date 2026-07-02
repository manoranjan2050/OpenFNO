<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'underlying' => ['sometimes', 'string', 'max:30'],
            'strategy_id' => ['nullable', 'integer', 'exists:strategies,id'],
            'strategy_name' => ['nullable', 'string', 'max:100'],
            'opened_at' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string', 'max:65000'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],

            // Replacing legs is only allowed while the trade is open (enforced in controller)
            'legs' => ['sometimes', 'array', 'min:1'],
            'legs.*.instrument_type' => ['required_with:legs', 'in:FUT,CE,PE'],
            'legs.*.expiry_date' => ['required_with:legs', 'date'],
            'legs.*.strike' => ['required_unless:legs.*.instrument_type,FUT', 'nullable', 'numeric', 'min:0'],
            'legs.*.side' => ['required_with:legs', 'in:BUY,SELL'],
            'legs.*.lots' => ['required_with:legs', 'integer', 'min:1', 'max:10000'],
            'legs.*.lot_size' => ['required_with:legs', 'integer', 'min:1', 'max:100000'],
            'legs.*.entry_price' => ['required_with:legs', 'numeric', 'min:0'],
            'legs.*.entry_at' => ['nullable', 'date'],
            'legs.*.exit_price' => ['nullable', 'numeric', 'min:0'],
            'legs.*.exit_at' => ['nullable', 'date'],
            'legs.*.tradingsymbol' => ['nullable', 'string', 'max:60'],
            'legs.*.instrument_id' => ['nullable', 'integer', 'exists:instruments,id'],
        ];
    }
}
