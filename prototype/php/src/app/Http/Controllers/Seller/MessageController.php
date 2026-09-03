<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\PostMessage;
use App\Domain\Auth\ActorType;
use App\Domain\Messaging\ConversationKind;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\MessagesQueryRequest;
use App\Http\Requests\Seller\PostMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Seller;
use App\Support\ListPaneWindow;
use App\Support\Messaging\InboxQuery;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class MessageController extends SellerController
{
    /** The Type checkbox → kind mapping (docs/messaging.md § "Inbox
     * filters and the seller's queue"). Support is the seller's own desk
     * kind — the seller never sees `AdminCustomer`. */
    private const array TYPE_KINDS = [
        'questions' => [ConversationKind::ListingQuestion],
        'orders' => [ConversationKind::Fulfillment],
        'support' => [ConversationKind::AdminSeller],
    ];

    public function index(MessagesQueryRequest $request): View
    {
        $seller = $this->seller();
        $query = $request->inboxQuery();

        $window = ListPaneWindow::of($this->conversationsQuery($seller, $query));

        return view('seller.messages.index', [
            'conversations' => $window->items,
            'conversationsTotal' => $window->total,
            'viewer' => ActorType::Seller,
            'query' => $query,
        ]);
    }

    public function show(Conversation $conversation, MarkConversationRead $markRead, MessagesQueryRequest $request): View
    {
        $this->authorize('view', $conversation);

        $seller = $this->seller();
        $markRead($conversation, $seller, $this->now());

        // The list pane reads the same `domain`/`type`/`status` the inbox
        // row that linked here carried (validated the same way index's own
        // query is, so an unrecognised value still answers 400) — an absent
        // one defaults to every status rather than index's Open-only, so a
        // direct or bookmarked visit to a resolved thread still lands in its
        // own pane. `paneFor` below is what keeps the selected thread in the
        // pane even where it falls outside that window.
        $query = $request->paneQuery();

        $pane = $this->paneFor($seller, $query, $conversation);

        return view('seller.messages.show', [
            ...$this->threadView($conversation, $this->replyToId($request)),
            'cellConversations' => $pane['items'],
            'cellConversationsTotal' => $pane['total'],
            'query' => $query,
        ]);
    }

    public function store(PostMessageRequest $request, Conversation $conversation, MessagesQueryRequest $queryRequest, PostMessage $postMessage, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $seller = $this->seller();
        // The composer's own action URL carries the pane's current
        // selection onward (`$paneRouteParams` in the thread component), so
        // reading it here is what keeps a reply's redirect from snapping the
        // pane back to the index route's defaults.
        $query = $queryRequest->paneQuery();

        try {
            $rateLimit->check(RateLimitName::MessagePost, (string) $seller->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/alignment.md §3: a form that trips comes back rather than
            // being replaced by the site's bare 429 page, so the thread the
            // seller was reading re-renders with the reply still in the box.
            $request->flash();

            $pane = $this->paneFor($seller, $query, $conversation);

            return $this->tooManyRequests($exceeded, 'seller.messages.show', [
                ...$this->threadView($conversation, $this->replyToId($request)),
                'cellConversations' => $pane['items'],
                'cellConversationsTotal' => $pane['total'],
                'query' => $query,
            ]);
        }

        $postMessage($conversation, $seller, $request->body(), $this->now(), $request->replyTo());

        return redirect()->route('seller.messages.show', ['conversation' => $conversation, ...$query->toRouteParams()]);
    }

    /**
     * The seller's inbox, narrowed by the current domain tab and the
     * popover's Type/Status choices — the one query the index route and the
     * show route's list pane both read through.
     *
     * @return Builder<Conversation>
     */
    private function conversationsQuery(Seller $seller, InboxQuery $query): Builder
    {
        $eloquent = Conversation::query()->withParticipant($seller);

        $domainKinds = match ($query->domain) {
            'buyers' => [ConversationKind::ListingQuestion, ConversationKind::Fulfillment],
            'support' => [ConversationKind::AdminSeller],
            default => null, // 'all': every kind the seller participates in.
        };
        $kinds = InboxQuery::intersectKinds($domainKinds, $this->typeKinds($query->types));

        if ($kinds !== null) {
            $eloquent->ofKind(...$kinds);
        }

        $this->applyStatuses($eloquent, $query->statuses);

        return $eloquent
            ->with(['seller', 'customer', 'admin', 'listing', 'fulfillment', 'latestMessage'])
            ->withUnreadCountFor($seller)
            ->unreadFirst();
    }

    /**
     * A thread's list pane, guaranteed to include it. `ListPaneWindow`'s own
     * `mustInclude` only rescues a row that sorts outside the window's SIZE
     * cap — it re-reads the same filtered query, so a domain, type, or
     * status that excludes the thread outright leaves it out too. This adds
     * one more, unscoped fetch for exactly that case.
     *
     * @return array{items: Collection<int, Model>, total: int}
     */
    private function paneFor(Seller $seller, InboxQuery $query, Conversation $conversation): array
    {
        $window = ListPaneWindow::of($this->conversationsQuery($seller, $query), $conversation);
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
     * The Type group's kind restriction, or null when every type is
     * selected (nothing to restrict).
     *
     * @param  list<string>  $types
     * @return list<ConversationKind>|null
     */
    private function typeKinds(array $types): ?array
    {
        if (count($types) === count(MessagesQueryRequest::TYPES)) {
            return null;
        }

        $kinds = [];
        foreach ($types as $type) {
            array_push($kinds, ...self::TYPE_KINDS[$type]);
        }

        return $kinds;
    }

    /**
     * The Status group's predicate, OR'd within the group — skipped
     * entirely when both Open and Resolved are selected, since together
     * they cover every conversation.
     *
     * @param  Builder<Conversation>  $query
     * @param  list<string>  $statuses
     */
    private function applyStatuses(Builder $query, array $statuses): void
    {
        if (in_array('open', $statuses, true) && in_array('resolved', $statuses, true)) {
            return;
        }

        $query->where(function (Builder $scoped) use ($statuses): void {
            foreach ($statuses as $status) {
                match ($status) {
                    'open' => $scoped->orWhereNull('resolved_at'),
                    'resolved' => $scoped->orWhereNotNull('resolved_at'),
                    default => null, // MessagesQueryRequest already refused anything else.
                };
            }
        });
    }

    /**
     * The thread page's data. The read mark is not part of it: a trip leaves
     * the world alone, and only `show()` marks the thread read.
     *
     * @return array<string, mixed>
     */
    private function threadView(Conversation $conversation, ?string $replyToId): array
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
        ];
    }

    /**
     * `?reply_to=` names a message on the URL; a failed reply flashes the
     * same id back through `old()` so the "Replying to…" block survives the
     * round trip. Either way it is read against the thread's own messages,
     * already loaded, rather than a fresh query — which is what makes a
     * stray or cross-thread id resolve to nothing rather than 500.
     */
    private function replyToId(Request $request): ?string
    {
        $old = old('reply_to_message_id');

        if (is_string($old) && $old !== '') {
            return $old;
        }

        $queried = $request->query('reply_to');

        return is_string($queried) && $queried !== '' ? $queried : null;
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
