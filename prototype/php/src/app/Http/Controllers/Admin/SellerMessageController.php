<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\OpenConversationWithMessage;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Domain\Reports\ListingStatusTally;
use App\Http\Requests\Admin\SendMessageRequest;
use App\Models\LedgerEntry;
use App\Models\Seller;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class SellerMessageController extends AdminController
{
    public function __invoke(
        Seller $seller,
        SendMessageRequest $request,
        OpenConversationWithMessage $send,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        $admin = $this->admin();

        try {
            $rateLimit->check(RateLimitName::MessagePost, (string) $admin->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/alignment.md §3: a form that trips comes back rather than
            // being replaced by the site's bare 429 page, so the seller page
            // the form sits on re-renders with the message still in the box.
            $request->flash();

            return $this->tooManyRequests($exceeded, 'admin.sellers.show', $this->sellerPage($seller));
        }

        $conversation = $send(
            ConversationSubject::adminSeller($admin->id, $seller->id),
            $admin,
            $request->body(),
            $this->now(),
        );

        return redirect()->route('admin.messages.show', $conversation);
    }

    /**
     * The seller page the message form sits on, the same data
     * `SellerController::show` renders it from.
     *
     * @return array<string, mixed>
     */
    private function sellerPage(Seller $seller): array
    {
        return [
            'seller' => $seller,
            'tally' => ListingStatusTally::from($seller->listingCountsByStatus()),
            'listings' => $seller->listings()->with('activeRemoval')->orderByDesc('created_at')->orderByDesc('id')->get(),
            'fulfillments' => $seller->fulfillments()->with('order')->orderByDesc('created_at')->orderByDesc('id')->get(),
            'payouts' => $seller->payouts()->orderByDesc('period_start')->get(),
            'balance' => $seller->escrowBalance(),
            // DSGN-006: the show page's list pane, the same as
            // `SellerController::show` renders it from.
            'cellSellers' => Seller::query()->withCount(['listings', 'fulfillments'])->orderByDesc('created_at')->orderByDesc('id')->get(),
            'cellBalances' => LedgerEntry::balancesBySeller(),
        ];
    }
}
