<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Listings\ListingDraft;
use App\Domain\Money\Money;
use App\Models\Listing;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        // A listing that already offers a choice or breaks into serialized
        // pieces no longer owns its price and stock count — the Basics
        // screen doesn't render those fields for it, so nothing requires
        // them here either.
        $ownsPriceAndStock = $this->listingOwnsPriceAndStock();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'dimensions' => ['nullable', 'string', 'max:255'],
            'price' => [Rule::requiredIf($ownsPriceAndStock), 'nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'quantity' => [Rule::requiredIf($ownsPriceAndStock), 'nullable', 'integer', 'min:0', 'max:999'],
            'category_id' => ['nullable', 'string', Rule::exists('categories', 'id')],
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

    /**
     * A configured listing's Basics save carries no price or quantity field
     * at all — the Basics screen never renders them — so the draft keeps
     * whatever the listing already holds rather than parsing an absent
     * input. `listings.price_cents` may be sync-derived at that point
     * ({@see \App\Support\Configurator\ListingPriceSync}); reading it back
     * unchanged here is what keeps a Basics save from clobbering it.
     */
    public function toDraft(): ListingDraft
    {
        $listing = $this->route('listing');
        $listing = $listing instanceof Listing ? $listing : null;

        return ListingDraft::of(
            $this->string('title')->toString(),
            $this->optionalString('description'),
            $this->optionalString('dimensions'),
            $this->filled('price') ? Money::fromDollars($this->string('price')->toString()) : ($listing === null ? Money::zero() : $listing->price()),
            $this->filled('quantity') ? $this->integer('quantity') : ($listing === null ? 0 : $listing->quantity),
            $this->optionalString('category_id'),
        );
    }

    private function optionalString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->toString() : null;
    }

    /**
     * Whether price and quantity are required on this submission: always on
     * a create (there is no listing yet to fall back to), and on an update
     * only while the listing still owns its own price and stock.
     */
    private function listingOwnsPriceAndStock(): bool
    {
        $listing = $this->route('listing');

        return ! $listing instanceof Listing || $listing->hasOwnPriceAndStock();
    }
}
