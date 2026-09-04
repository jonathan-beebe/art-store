<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Messaging\OpenThread;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\Messaging\ThreadTitle;
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
        OpenThread $ask,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        $visitor = $this->visitor();
        $body = $request->body();

        try {
            $rateLimit->check(RateLimitName::ConversationOpen, (string) $visitor->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/spec.md §3: a form that trips comes back rather than
            // being replaced by the site's bare 429 page, so the listing the
            // shopper was reading re-renders with the question still in the
            // box.
            $request->flash();

            return $this->tooManyRequests($exceeded, 'shop.listing', ListingPagePresenter::forShop($listing, $visitor, $request));
        }

        $conversation = $ask(
            ThreadOpening::listingQuestion($listing->seller_id, $visitor->id, $listing->id, ThreadTitle::fromBody($body->value)),
            $visitor,
            $body,
            $this->now(),
        );

        return redirect()->route('shop.messages.show', $conversation);
    }
}
