<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\OpenThread;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\OpenSupportThreadRequest;
use App\Models\Seller;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The seller's "New conversation with Art Store Support" screen: a titled
 * thread the seller composes rather than an empty one they land on, so the
 * desk sees a subject line before the seller has typed a word.
 */
final class SupportController extends SellerController
{
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
