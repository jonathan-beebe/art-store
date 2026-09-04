<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Listings\ListingCreationShape;
use Illuminate\Validation\Rule;

/**
 * The new-listing question screen's `?shape=`/`?title=`, submitted back to
 * the same route by GET so a shape typed into the address bar (or
 * bookmarked) reopens exactly where Continue left off.
 */
final class ListingCreateRequest extends SellerQueryRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'shape' => ['nullable', Rule::enum(ListingCreationShape::class)],
            'title' => ['nullable', 'string'],
        ];
    }

    public function shape(): ?ListingCreationShape
    {
        return $this->enum('shape', ListingCreationShape::class);
    }

    public function title(): ?string
    {
        return $this->stringOrNull('title');
    }
}
