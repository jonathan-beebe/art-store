<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Messaging\OpenConversationWithMessage;
use App\Domain\Listings\ListingAvailability;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Shop\AskSellerRequest;
use App\Models\Customer;
use App\Models\Listing;
use App\Support\Configurator\ConfiguratorInput;
use App\Support\Configurator\ConfiguratorPageResolver;
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

            return $this->tooManyRequests($exceeded, 'shop.listing', $this->listingPage($listing, $visitor, $request));
        }

        $conversation = $ask(
            ConversationSubject::listingQuestion($listing->seller_id, $visitor->id, $listing->id),
            $visitor,
            $request->body(),
            $this->now(),
        );

        return redirect()->route('shop.messages.show', $conversation);
    }

    /**
     * The listing page the question form sits on, the same data
     * `ListingController` renders it from. The view it records there is not
     * part of it: a trip leaves the world alone.
     *
     * @return array<string, mixed>
     */
    private function listingPage(Listing $listing, Customer $visitor, AskSellerRequest $request): array
    {
        $hasConfigurator = ConfiguratorPageResolver::hasConfigurator($listing);

        return [
            'listing' => $listing->load('seller', 'faqs'),
            'isPurchasable' => ListingAvailability::isPurchasable($listing->status, $listing->quantity),
            'isFavorited' => $visitor->favorites()->where('listing_id', $listing->id)->exists(),
            'hasConfigurator' => $hasConfigurator,
            'configuration' => $hasConfigurator ? ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::fromQuery($request)) : null,
        ];
    }
}
