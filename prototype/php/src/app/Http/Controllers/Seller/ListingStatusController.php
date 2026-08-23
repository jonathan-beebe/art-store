<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Listings\ChangeListingStatus;
use App\Domain\Listings\ListingStatus;
use App\Domain\Reports\StatusLabel;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ListingStatusController extends Controller
{
    public function __invoke(Request $request, string $listing, ChangeListingStatus $changeListingStatus): RedirectResponse
    {
        $listing = auth('seller')->user()->listings()->findOrFail($listing);

        $submitted = $request->validate([
            'status' => ['required', Rule::in($this->allowedTransitions($listing))],
        ]);

        $next = ListingStatus::from($submitted['status']);
        $changeListingStatus($listing, $next);

        return redirect()
            ->route('seller.listings.index')
            ->with('status', "\"{$listing->title}\" is now ".lcfirst(StatusLabel::of($next)).'.');
    }

    /**
     * @return list<string>
     */
    private function allowedTransitions(Listing $listing): array
    {
        return array_map(fn (ListingStatus $status): string => $status->value, $listing->status->transitions());
    }
}
