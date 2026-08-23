<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Messaging\OpenConversation;
use App\Domain\Messaging\ConversationSubject;
use App\Models\Fulfillment;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

final class OrderMessageController extends ShopController
{
    /**
     * The route scopes the fulfillment to the order, so the only ownership
     * left to settle is the order's.
     */
    public function __invoke(Order $order, Fulfillment $fulfillment, OpenConversation $openConversation): RedirectResponse
    {
        $this->authorizeVisitor('view', $order);

        $conversation = $openConversation(
            ConversationSubject::fulfillment($fulfillment->seller_id, $order->customer_id, $fulfillment->id),
            $this->now(),
        );

        return redirect()->route('shop.messages.show', $conversation);
    }
}
