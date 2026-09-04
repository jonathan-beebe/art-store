<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\OpenConversation;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\RateLimiting\RateLimitName;
use App\Models\Fulfillment;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;

final class OrderMessageController extends SellerController
{
    public function __invoke(Fulfillment $fulfillment, OpenConversation $openConversation, RateLimitGate $rateLimit): RedirectResponse
    {
        $this->authorize('view', $fulfillment);
        $rateLimit->check(RateLimitName::ConversationOpen, (string) $this->seller()->id);

        $fulfillment->loadMissing('order');

        $conversation = $openConversation(
            ConversationSubject::fulfillment($this->seller()->id, $fulfillment->order->customer_id, $fulfillment->id),
            $this->now(),
        );

        return redirect()->route('seller.messages.show', $conversation);
    }
}
