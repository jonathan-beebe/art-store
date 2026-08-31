<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Messaging\OpenConversationWithMessage;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Shop\AskSellerRequest;
use App\Models\Listing;
use App\Support\Configurator\ListingPagePresenter;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class ListingQuestionController extends ShopController
{
    public function __invoke(
        Listing $listing,
        AskSellerRequest $request,
        OpenConversationWithMessage $ask,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        $visitor = $this->visitor();

        try {
            $rateLimit->check(RateLimitName::ConversationOpen, (string) $visitor->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/alignment.md §3: a form that trips comes back rather than
            // being replaced by the site's bare 429 page, so the listing the
            // shopper was reading re-renders with the question still in the
            // box.
            $request->flash();

            return $this->tooManyRequests($exceeded, 'shop.listing', ListingPagePresenter::forShop($listing, $visitor, $request));
        }

        $conversation = $ask(
            ConversationSubject::listingQuestion($listing->seller_id, $visitor->id, $listing->id),
            $visitor,
            $request->body(),
            $this->now(),
        );

        return redirect()->route('shop.messages.show', $conversation);
    }
}
