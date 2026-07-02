<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseTradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'closed_at' => ['nullable', 'date'],
            'legs' => ['required', 'array', 'min:1'],
            'legs.*.id' => ['required', 'integer'],
            'legs.*.exit_price' => ['required', 'numeric', 'min:0'],
            'legs.*.exit_at' => ['nullable', 'date'],
        ];
    }
}
