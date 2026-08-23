<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

final class AddToCartRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * The listing page posts an "Add to cart" button with no quantity field,
     * which means one.
     */
    public function quantity(): int
    {
        return $this->filled('quantity') ? $this->integer('quantity') : 1;
    }
}
