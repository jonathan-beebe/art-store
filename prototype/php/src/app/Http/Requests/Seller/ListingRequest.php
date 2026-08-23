<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Listings\ListingDraft;
use App\Domain\Money\Money;
use Illuminate\Foundation\Http\FormRequest;

final class ListingRequest extends FormRequest
{
    private const MAX_IMAGE_KILOBYTES = 5120;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'medium' => ['nullable', 'string', 'max:255'],
            'dimensions' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
            // `image` and `mimes` both read the declared type, which an upload
            // controls. `dimensions` decodes the file, so a text file renamed
            // .jpg is rejected here rather than served as a broken listing image.
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,png,webp,gif', 'dimensions:min_width=1,min_height=1', 'max:'.self::MAX_IMAGE_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.regex' => 'The price is an amount in dollars, like 249.00.',
        ];
    }

    public function toDraft(): ListingDraft
    {
        return new ListingDraft(
            $this->string('title')->toString(),
            $this->input('description'),
            $this->input('medium'),
            $this->input('dimensions'),
            Money::fromDollars($this->string('price')->toString()),
            $this->integer('quantity'),
        );
    }
}
