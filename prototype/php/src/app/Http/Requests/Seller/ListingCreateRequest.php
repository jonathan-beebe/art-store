<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Listings\ListingCreationShape;

/**
 * The new-listing question screen's `?shape=`/`?title=`, submitted back to
 * the same route by GET so a shape typed into the address bar (or
 * bookmarked) reopens exactly where Continue left off. DSGN-003's own call:
 * an unrecognised `shape` normalizes to null rather than answering 400, the
 * same as a `shape` never submitted — either way the screen falls back to
 * the question it opens on.
 */
final class ListingCreateRequest extends SellerQueryRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'shape' => ['nullable', 'string'],
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
