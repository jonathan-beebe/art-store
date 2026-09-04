<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\OpenThread;
use App\Analytics\Admin\Funnel;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\FunnelDefinition;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Domain\Reports\ListingStatusTally;
use App\Http\Requests\Admin\OpenSellerThreadRequest;
use App\Models\LedgerEntry;
use App\Models\Seller;
use App\Support\ListPaneWindow;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Opens a fresh, titled admin/seller thread from the seller's detail page —
 * every submission opens its own thread rather than finding one, the way a
 * fresh-opened kind always does.
 */
final class SellerMessageController extends AdminController
{
    /** The seller page's funnel panel has no range control — see
     * SellerController::FUNNEL_RANGE_DAYS, the same window. */
    private const int FUNNEL_RANGE_DAYS = 30;

    public function __invoke(
        Seller $seller,
        OpenSellerThreadRequest $request,
        OpenThread $send,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        $admin = $this->admin();

        try {
            $rateLimit->check(RateLimitName::ConversationOpen, (string) $admin->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/spec.md §3: a form that trips comes back rather than
            // being replaced by the site's bare 429 page, so the seller page
            // the form sits on re-renders with the message still in the box.
            $request->flash();

            return $this->tooManyRequests($exceeded, 'admin.sellers.show', $this->sellerPage($seller));
        }

        $conversation = $send(
            ThreadOpening::adminSeller($seller->id, $request->title(), $request->fulfillmentId()),
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
        // DSGN-006: the show page's list pane, windowed the same way
        // `SellerController::show` windows it (`ListPaneWindow`, DSGN-006
        // follow-up).
        $window = ListPaneWindow::of(
            Seller::query()->withCount(['listings', 'fulfillments'])->with('storeProfile')->orderByDesc('created_at')->orderByDesc('id'),
            $seller,
        );

        return [
            'seller' => $seller->loadMissing('storeProfile'),
            'funnel' => Funnel::forSeller(FunnelDefinition::storefront(), $seller, AnalyticsRange::of(self::FUNNEL_RANGE_DAYS, $this->now())),
            'tally' => ListingStatusTally::from($seller->listingCountsByStatus()),
            'listings' => $seller->listings()->with('activeRemoval')->orderByDesc('created_at')->orderByDesc('id')->get(),
            'fulfillments' => $seller->fulfillments()->with('order')->orderByDesc('created_at')->orderByDesc('id')->get(),
            'payouts' => $seller->payouts()->orderByDesc('period_start')->get(),
            'balance' => $seller->escrowBalance(),
            'cellSellers' => $window->items,
            'cellSellersTotal' => $window->total,
            'cellBalances' => LedgerEntry::balancesBySeller(),
        ];
    }
}
