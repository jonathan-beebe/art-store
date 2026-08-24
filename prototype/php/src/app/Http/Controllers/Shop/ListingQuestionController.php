<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Messaging\OpenConversation;
use App\Actions\Messaging\PostMessage;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Shop\AskSellerRequest;
use App\Models\Listing;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;

final class ListingQuestionController extends ShopController
{
    public function __invoke(
        Listing $listing,
        AskSellerRequest $request,
        OpenConversation $openConversation,
        PostMessage $postMessage,
        RateLimitGate $rateLimit,
    ): RedirectResponse {
        $visitor = $this->visitor();
        $rateLimit->check(RateLimitName::ConversationOpen, (string) $visitor->id);

        $conversation = $openConversation(
            ConversationSubject::listingQuestion($listing->seller_id, $visitor->id, $listing->id),
            $this->now(),
        );

        $this->authorizeVisitor('post', $conversation);

        $postMessage($conversation, $visitor, $request->body(), $this->now());

        return redirect()->route('shop.messages.show', $conversation);
    }
}
