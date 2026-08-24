<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\PostMessage;
use App\Domain\Auth\ActorType;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Shop\PostMessageRequest;
use App\Models\Conversation;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class MessageController extends ShopController
{
    public function index(): View
    {
        $visitor = $this->visitor();

        $conversations = Conversation::query()
            ->withParticipant($visitor)
            ->with(['seller', 'customer', 'admin', 'listing', 'fulfillment', 'latestMessage'])
            ->withUnreadCountFor($visitor)
            ->orderByDesc('last_message_at')
            ->get();

        return view('shop.messages.index', ['conversations' => $conversations, 'viewer' => ActorType::Customer]);
    }

    public function show(Conversation $conversation, MarkConversationRead $markRead): View
    {
        $this->authorizeVisitor('view', $conversation);

        $markRead($conversation, $this->visitor(), $this->now());

        return view('shop.messages.show', $this->threadView($conversation));
    }

    public function store(PostMessageRequest $request, Conversation $conversation, PostMessage $postMessage, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $visitor = $this->visitor();

        try {
            $rateLimit->check(RateLimitName::MessagePost, (string) $visitor->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/alignment.md §3: a form that trips comes back rather than
            // being replaced by the site's bare 429 page, so the thread the
            // shopper was reading re-renders with the reply still in the box.
            $request->flash();

            return $this->tooManyRequests($exceeded, 'shop.messages.show', $this->threadView($conversation));
        }

        $postMessage($conversation, $visitor, $request->body(), $this->now());

        return redirect()->route('shop.messages.show', $conversation);
    }

    /**
     * The thread page's data. The read mark is not part of it: a trip leaves
     * the world alone, and only `show()` marks the thread read.
     *
     * @return array<string, mixed>
     */
    private function threadView(Conversation $conversation): array
    {
        $conversation->load([
            'seller', 'customer', 'admin', 'listing', 'fulfillment',
            'messages' => fn (Relation $query): Relation => $query->orderBy('sent_at')->orderBy('id'),
            'messages.sender',
        ]);

        return [
            'conversation' => $conversation,
            'viewer' => ActorType::Customer,
        ];
    }
}
