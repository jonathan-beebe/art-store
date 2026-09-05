<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\OpenThread;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\OpenSupportThreadRequest;
use App\Models\Seller;
use App\RateLimiting\RateLimitGate;
use App\Seller\HelpArticles;
use App\Seller\SupportDesk;
use App\Seller\SupportThreads;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The support hub, the desk's "New conversation" form, and its submit —
 * one controller for the seller's whole support surface, since the three
 * screens share one thing to say: here is the desk, and here is how to
 * reach it.
 */
final class SupportController extends SellerController
{
    public function index(HelpArticles $helpArticles): View
    {
        $seller = $this->seller();

        return view('seller.support.index', [
            'desk' => SupportDesk::for($seller, $this->now()),
            'helpGroups' => $helpArticles->grouped(),
            'threads' => SupportThreads::for($seller),
        ]);
    }

    public function create(): View
    {
        return view('seller.support.create', [
            'fulfillments' => $this->recentFulfillments($this->seller()),
        ]);
    }

    public function store(OpenSupportThreadRequest $request, OpenThread $openThread, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $seller = $this->seller();

        try {
            $rateLimit->check(RateLimitName::ConversationOpen, (string) $seller->id);
        } catch (RateLimitExceeded $exceeded) {
            $request->flash();

            return $this->tooManyRequests($exceeded, 'seller.support.create', [
                'fulfillments' => $this->recentFulfillments($seller),
            ]);
        }

        $conversation = $openThread(
            ThreadOpening::adminSeller($seller->id, $request->title(), $request->fulfillmentId()),
            $seller,
            $request->body(),
            $this->now(),
        );

        return redirect()->route('seller.messages.show', $conversation);
    }

    /**
     * @return Collection<int, \App\Models\Fulfillment>
     */
    private function recentFulfillments(Seller $seller): Collection
    {
        return $seller->fulfillments()
            ->with('order')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();
    }
}
