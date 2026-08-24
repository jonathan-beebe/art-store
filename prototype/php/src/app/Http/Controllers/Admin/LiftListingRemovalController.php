<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Listings\LiftListingRemoval;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;

final class LiftListingRemovalController extends Controller
{
    public function __invoke(Listing $listing, LiftListingRemoval $liftListingRemoval): RedirectResponse
    {
        $liftListingRemoval($listing, $this->now());

        return redirect()->route('admin.listings.show', $listing)->with('status', 'Removal lifted.');
    }
}
