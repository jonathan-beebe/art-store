<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\OpenConversation;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\RateLimiting\RateLimitName;
use App\Domain\Seller\CustomerRow;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Seller;
use App\Seller\SellerCustomers;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;

/**
 * The customer page's Message button. A buyer the seller has already
 * written to opens their newest thread; one they have not opens the thread
 * for the buyer's latest parcel, which is the subject the two of them
 * share.
 */
final class CustomerMessageController extends SellerController
{
    public function __invoke(Customer $customer, OpenConversation $openConversation, RateLimitGate $rateLimit): RedirectResponse
    {
        $seller = $this->seller();

        abort_if(! SellerCustomers::forCustomer($seller, $customer) instanceof CustomerRow, 404);

        $existing = $seller->conversations()
            ->where('customer_id', $customer->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();

        if ($existing instanceof Conversation) {
            return redirect()->route('seller.messages.show', $existing);
        }

        $rateLimit->check(RateLimitName::ConversationOpen, (string) $seller->id);

        $conversation = $openConversation(
            ConversationSubject::fulfillment($seller->id, $customer->id, $this->latestParcel($seller, $customer)),
            $this->now(),
        );

        return redirect()->route('seller.messages.show', $conversation);
    }

    /**
     * The buyer's newest counted parcel with this seller, newest by when the
     * order was placed — the recency the customers section reads
     * everywhere. A row on the customer page means there is one.
     */
    private function latestParcel(Seller $seller, Customer $customer): string
    {
        return (string) $seller->fulfillments()
            ->where('fulfillments.customer_id', $customer->id)
            ->counted()
            ->join('orders', 'orders.id', '=', 'fulfillments.order_id')
            ->orderByDesc('orders.placed_at')
            ->orderByDesc('fulfillments.id')
            ->select('fulfillments.*')
            ->firstOrFail()
            ->id;
    }
}
