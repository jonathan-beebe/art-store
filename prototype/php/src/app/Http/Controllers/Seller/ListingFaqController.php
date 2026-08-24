<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\PublishListingFaq;
use App\Actions\Messaging\UnpublishListingFaq;
use App\Actions\Messaging\UpdateListingFaq;
use App\Http\Requests\Seller\PublishFaqRequest;
use App\Http\Requests\Seller\UpdateFaqRequest;
use App\Models\Listing;
use App\Models\ListingFaq;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class ListingFaqController extends SellerController
{
    public function index(Listing $listing): View
    {
        $this->authorize('view', $listing);

        return view('seller.listings.faqs.index', [
            'listing' => $listing,
            'faqs' => $listing->faqs()->orderByDesc('created_at')->orderByDesc('id')->get(),
        ]);
    }

    public function store(PublishFaqRequest $request, Listing $listing, PublishListingFaq $publish): RedirectResponse
    {
        $publish($listing, $request->draft(), $request->sourceMessage(), $this->now());

        return redirect()->route('seller.listings.faqs.index', $listing)->with('status', 'Published to the listing.');
    }

    public function update(UpdateFaqRequest $request, Listing $listing, ListingFaq $faq, UpdateListingFaq $update): RedirectResponse
    {
        $update($faq, $request->draft(), $this->now());

        return redirect()->route('seller.listings.faqs.index', $listing)->with('status', 'Updated.');
    }

    public function destroy(Listing $listing, ListingFaq $faq, UnpublishListingFaq $unpublish): RedirectResponse
    {
        $this->authorize('update', $listing);

        $unpublish($faq, $this->now());

        return redirect()->route('seller.listings.faqs.index', $listing)->with('status', 'Unpublished.');
    }
}
