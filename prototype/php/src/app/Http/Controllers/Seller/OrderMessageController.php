<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\OpenConversation;
use App\Domain\Messaging\ConversationSubject;
use App\Models\Fulfillment;
use Illuminate\Http\RedirectResponse;

final class OrderMessageController extends SellerController
{
    public function __invoke(Fulfillment $fulfillment, OpenConversation $openConversation): RedirectResponse
    {
        $this->authorize('view', $fulfillment);

        $fulfillment->loadMissing('order');

        $conversation = $openConversation(
            ConversationSubject::fulfillment($this->seller()->id, $fulfillment->order->customer_id, $fulfillment->id),
            $this->now(),
        );

        return redirect()->route('seller.messages.show', $conversation);
    }
}
