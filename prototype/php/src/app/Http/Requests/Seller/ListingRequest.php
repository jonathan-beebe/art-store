<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Listings\ListingDraft;
use App\Domain\Money\Money;
use App\Models\Listing;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Override;

final class ListingRequest extends FormRequest
{
    private const MAX_IMAGE_KILOBYTES = 5120;

    /**
     * A create names no listing to own; an update names one, and it is the
     * signed-in seller's or it does not exist for them.
     */
    public function authorize(): Response
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? Gate::inspect('update', $listing)
            : Response::allow();
    }

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
    #[Override]
    public function messages(): array
    {
        return [
            'price.regex' => 'The price is an amount in dollars, like 249.00.',
        ];
    }

    public function toDraft(): ListingDraft
    {
        return ListingDraft::of(
            $this->string('title')->toString(),
            $this->optionalString('description'),
            $this->optionalString('medium'),
            $this->optionalString('dimensions'),
            Money::fromDollars($this->string('price')->toString()),
            $this->integer('quantity'),
        );
    }

    private function optionalString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->toString() : null;
    }
}
