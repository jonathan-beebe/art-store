<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Listing;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * The attributes section's save: one array entry per property control on the
 * screen, each holding the property_value ids checked for it. Membership —
 * whether a property or value belongs to the listing's current category —
 * is {@see \App\Actions\Configurator\SetListingAttributes}'s to judge, the
 * same division {@see ModifierScopeRequest} draws for a modifier's scope.
 */
final class ListingAttributeRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->listing());
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'attribute' => ['array'],
            'attribute.*' => ['array'],
            'attribute.*.*' => ['string'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function selections(): array
    {
        /** @var array<string, list<string>> $selections */
        $selections = $this->input('attribute', []);

        return $selections;
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The listing attributes route binds a listing.');
    }
}
