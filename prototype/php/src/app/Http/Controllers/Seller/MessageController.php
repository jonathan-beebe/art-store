<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\PostMessage;
use App\Domain\Auth\ActorType;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Domain\Seller\MessageDomain;
use App\Http\Requests\Seller\MessagesQueryRequest;
use App\Http\Requests\Seller\PostMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Seller;
use App\Seller\ThreadContext;
use App\Support\ListPaneWindow;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class MessageController extends SellerController
{
    public function index(MessagesQueryRequest $request): View
    {
        $seller = $this->seller();
        $domain = $request->domain();

        $window = ListPaneWindow::of($this->conversationsQuery($seller, $domain));

        return view('seller.messages.index', [
            'conversations' => $window->items,
            'conversationsTotal' => $window->total,
            'viewer' => ActorType::Seller,
            'domain' => $domain->value,
        ]);
    }

    public function show(Conversation $conversation, MarkConversationRead $markRead, MessagesQueryRequest $request): View
    {
        $this->authorize('view', $conversation);

        $seller = $this->seller();
        $markRead($conversation, $seller, $this->now());

        // The list pane reads the same `domain` the inbox row that linked
        // here carried (validated the same way index's own query is, so an
        // unrecognised value still answers 400). `paneFor` below is what
        // keeps the selected thread in the pane even where it falls outside
        // the current domain tab.
        $domain = $request->domain();

        $pane = $this->paneFor($seller, $domain, $conversation);

        return view('seller.messages.show', [
            ...$this->threadView($seller, $conversation, $this->replyToId($request)),
            'cellConversations' => $pane['items'],
            'cellConversationsTotal' => $pane['total'],
            'domain' => $domain->value,
        ]);
    }

    public function store(PostMessageRequest $request, Conversation $conversation, MessagesQueryRequest $queryRequest, PostMessage $postMessage, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $seller = $this->seller();
        // The composer's own action URL carries the pane's current domain
        // onward (`$paneRouteParams` in the thread component), so reading it
        // here is what keeps a reply's redirect from snapping the pane back
        // to the index route's default.
        $domain = $queryRequest->domain();

        try {
            $rateLimit->check(RateLimitName::MessagePost, (string) $seller->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/alignment.md §3: a form that trips comes back rather than
            // being replaced by the site's bare 429 page, so the thread the
            // seller was reading re-renders with the reply still in the box.
            $request->flash();

            $pane = $this->paneFor($seller, $domain, $conversation);

            return $this->tooManyRequests($exceeded, 'seller.messages.show', [
                ...$this->threadView($seller, $conversation, $this->replyToId($queryRequest)),
                'cellConversations' => $pane['items'],
                'cellConversationsTotal' => $pane['total'],
                'domain' => $domain->value,
            ]);
        }

        $postMessage($conversation, $seller, $request->body(), $this->now(), $request->replyTo());

        return redirect()->route('seller.messages.show', ['conversation' => $conversation, 'domain' => $domain->value]);
    }

    /**
     * The seller's inbox, narrowed by the current domain tab — the one
     * query the index route and the show route's list pane both read
     * through. Every status is listed; only the domain narrows it.
     *
     * @return Builder<Conversation>
     */
    private function conversationsQuery(Seller $seller, MessageDomain $domain): Builder
    {
        $eloquent = Conversation::query()->withParticipant($seller);

        $kinds = $domain->kinds();

        if ($kinds !== null) {
            $eloquent->ofKind(...$kinds);
        }

        return $eloquent
            ->with(['seller', 'customer', 'admin', 'listing', 'fulfillment', 'latestMessage'])
            ->withUnreadCountFor($seller)
            ->orderByDesc('last_message_at');
    }

    /**
     * A thread's list pane, guaranteed to include it. `ListPaneWindow`'s own
     * `mustInclude` only rescues a row that sorts outside the window's SIZE
     * cap — it re-reads the same filtered query, so a domain that excludes
     * the thread outright leaves it out too. This adds one more, unscoped
     * fetch for exactly that case.
     *
     * @return array{items: Collection<int, Conversation>, total: int}
     */
    private function paneFor(Seller $seller, MessageDomain $domain, Conversation $conversation): array
    {
        $window = ListPaneWindow::of($this->conversationsQuery($seller, $domain), $conversation);
        $items = $window->items;

        if (! $items->contains('id', '=', $conversation->id)) {
            $items = $items->prepend(
                Conversation::query()
                    ->with(['seller', 'customer', 'admin', 'listing', 'fulfillment', 'latestMessage'])
                    ->withUnreadCountFor($seller)
                    ->whereKey($conversation->id)
                    ->firstOrFail(),
            );
        }

        return ['items' => $items, 'total' => $window->total];
    }

    /**
     * The thread page's data. The read mark is not part of it: a trip leaves
     * the world alone, and only `show()` marks the thread read.
     *
     * @return array<string, mixed>
     */
    private function threadView(Seller $seller, Conversation $conversation, ?string $replyToId): array
    {
        $conversation->load([
            'seller', 'customer', 'admin', 'listing', 'fulfillment',
            'messages' => fn (Relation $query): Relation => $query->orderBy('sent_at')->orderBy('id'),
            'messages.sender',
            'messages.replyTo.sender',
        ]);

        return [
            'conversation' => $conversation,
            'faqPrefill' => $conversation->faqPrefill(),
            'viewer' => ActorType::Seller,
            'replyTo' => $this->resolveReplyTo($conversation, $replyToId),
            'context' => ThreadContext::forSeller($seller, $conversation, $this->now()),
        ];
    }

    /**
     * `?reply_to=` names a message on the URL; a failed reply flashes the
     * same id back through `old()` so the "Replying to…" block survives the
     * round trip. Either way it is read against the thread's own messages,
     * already loaded, rather than a fresh query — which is what makes a
     * stray or cross-thread id resolve to nothing rather than 500.
     */
    private function replyToId(MessagesQueryRequest $request): ?string
    {
        $old = old('reply_to_message_id');

        if (is_string($old) && $old !== '') {
            return $old;
        }

        return $request->replyTo();
    }

    private function resolveReplyTo(Conversation $conversation, ?string $replyToId): ?Message
    {
        if ($replyToId === null) {
            return null;
        }

        /** @var Message|null $message */
        $message = $conversation->messages->firstWhere('id', $replyToId);

        return $message;
    }
}
