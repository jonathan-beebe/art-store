<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

final class MarkShippedRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'carrier' => ['required', 'string', 'max:255'],
            'tracking_number' => ['required', 'string', 'max:255'],
        ];
    }
}
