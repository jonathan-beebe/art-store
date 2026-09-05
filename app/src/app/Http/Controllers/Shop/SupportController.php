<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Messaging\OpenThread;
use App\Domain\Messaging\ConversationKind;
use App\Domain\Messaging\ConversationStatus;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Shop\SupportRequest;
use App\Models\Conversation;
use App\Models\Customer;
use App\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * "Talk to us" — the storefront's way into the `admin_customer` desk.
 * `auth.customer` guards both routes, so the visitor is always a verified,
 * signed-in customer here.
 */
final class SupportController extends ShopController
{
    private const int RECENT_ORDERS_LIMIT = 20;

    private const int OPEN_CONVERSATIONS_LIMIT = 3;

    public function show(Request $request): View
    {
        $visitor = $this->visitor();

        return view('shop.support', [
            ...$this->formView($visitor),
            'preselectedOrderId' => $this->preselectedOrderId($request, $visitor),
        ]);
    }

    public function store(SupportRequest $request, OpenThread $open, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $visitor = $this->knownVisitor();

        try {
            $rateLimit->check(RateLimitName::ConversationOpen, (string) $visitor->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/spec.md §3: a form that trips comes back on its own page
            // at 429, so the visitor's subject and message stay in the
            // boxes.
            $request->flash();

            return $this->tooManyRequests($exceeded, 'shop.support', [
                ...$this->formView($visitor),
                'preselectedOrderId' => $request->orderId(),
            ]);
        }

        $conversation = $open(
            ThreadOpening::adminCustomer($visitor->id, $request->title(), $request->orderId()),
            $visitor,
            $request->body(),
            $this->now(),
        );

        return redirect()->route('shop.messages.show', $conversation);
    }

    /**
     * The order picker's options and the "your open conversations with us"
     * aside — the data both the fresh form and a rate-limited re-render need.
     *
     * @return array<string, mixed>
     */
    private function formView(Customer $visitor): array
    {
        return [
            'orders' => $visitor->orders()->with('items')->orderByDesc('placed_at')->limit(self::RECENT_ORDERS_LIMIT)->get(),
            'openConversations' => Conversation::query()
                ->withParticipant($visitor)
                ->ofKind(ConversationKind::AdminCustomer)
                ->withStatus(ConversationStatus::Open)
                ->orderByDesc('last_message_at')
                ->limit(self::OPEN_CONVERSATIONS_LIMIT)
                ->get(),
        ];
    }

    /**
     * `?order=` preselects the picker when it names one of the visitor's
     * own orders. Any other value is ignored.
     */
    private function preselectedOrderId(Request $request, Customer $visitor): ?string
    {
        $orderId = $request->query('order');

        return is_string($orderId) && $visitor->orders()->whereKey($orderId)->exists() ? $orderId : null;
    }
}
