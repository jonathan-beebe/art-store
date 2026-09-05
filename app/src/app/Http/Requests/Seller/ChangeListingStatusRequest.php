<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Listings\ListingStatus;
use App\Models\Listing;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;
use Stringable;

final class ChangeListingStatusRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->listing());
    }

    /**
     * @return array<string, list<string|Stringable>>
     */
    public function rules(): array
    {
        $transitions = $this->listing()->availableTransitions();

        // An empty `only` set would admit every case, so a status with
        // nowhere left to go marks the field `prohibited`, refusing it
        // outright.
        return [
            'status' => $transitions === []
                ? ['prohibited']
                : ['required', Rule::enum(ListingStatus::class)->only($transitions)],
        ];
    }

    public function status(): ListingStatus
    {
        return $this->enum('status', ListingStatus::class)
            ?? throw new RuntimeException('The status rule admits only listing statuses.');
    }

    private function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The status route binds a listing.');
    }
}
