<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\PostMessage;
use App\Domain\Auth\ActorType;
use App\Domain\Messaging\ConversationKind;
use App\Domain\Messaging\ConversationStatus;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\MessagesQueryRequest;
use App\Http\Requests\Seller\PostMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Seller;
use App\Support\ListPaneWindow;
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
    public function index(MessagesQueryRequest $request): View
    {
        $seller = $this->seller();
        $filter = $request->filter();
        $status = $request->status();

        $window = ListPaneWindow::of($this->conversationsQuery($seller, $filter, $status));

        return view('seller.messages.index', [
            'conversations' => $window->items,
            'conversationsTotal' => $window->total,
            'viewer' => ActorType::Seller,
            'filter' => $filter,
            'status' => $status,
            'filterCounts' => $this->filterCounts($seller, $status),
        ]);
    }

    public function show(Conversation $conversation, MarkConversationRead $markRead, MessagesQueryRequest $request): View
    {
        $this->authorize('view', $conversation);

        $seller = $this->seller();
        $markRead($conversation, $seller, $this->now());

        // The list pane reads the same `filter`/`status` the inbox row that
        // linked here carried (validated the same way index's own query is,
        // so an unrecognised value still answers 400) — `paneFor` below is
        // what keeps the selected thread in the pane even where it falls
        // outside that window.
        $filter = $request->filter();
        $status = $request->status();

        $pane = $this->paneFor($seller, $filter, $status, $conversation);

        return view('seller.messages.show', [
            ...$this->threadView($conversation, $this->replyToId($request)),
            'cellConversations' => $pane['items'],
            'cellConversationsTotal' => $pane['total'],
            'filter' => $filter,
            'status' => $status,
            'filterCounts' => $this->filterCounts($seller, $status),
        ]);
    }

    public function store(PostMessageRequest $request, Conversation $conversation, MessagesQueryRequest $queryRequest, PostMessage $postMessage, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $seller = $this->seller();

        try {
            $rateLimit->check(RateLimitName::MessagePost, (string) $seller->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/alignment.md §3: a form that trips comes back rather than
            // being replaced by the site's bare 429 page, so the thread the
            // seller was reading re-renders with the reply still in the box.
            $request->flash();

            $filter = $queryRequest->filter();
            $status = $queryRequest->status();

            $pane = $this->paneFor($seller, $filter, $status, $conversation);

            return $this->tooManyRequests($exceeded, 'seller.messages.show', [
                ...$this->threadView($conversation, $this->replyToId($request)),
                'cellConversations' => $pane['items'],
                'cellConversationsTotal' => $pane['total'],
                'filter' => $filter,
                'status' => $status,
                'filterCounts' => $this->filterCounts($seller, $status),
            ]);
        }

        $postMessage($conversation, $seller, $request->body(), $this->now(), $request->replyTo());

        return redirect()->route('seller.messages.show', $conversation);
    }

    /**
     * The seller's inbox, narrowed by `filter` and `status` — the one query
     * the index route, the show route's list pane, and the chip counts all
     * read through, so a chip's own count and the rows it links to can never
     * disagree about what they're counting.
     *
     * @return Builder<Conversation>
     */
    private function conversationsQuery(Seller $seller, string $filter, string $status): Builder
    {
        $query = Conversation::query()->withParticipant($seller);

        match ($filter) {
            'unread' => $query->unreadOnly($seller),
            'questions' => $query->ofKind(ConversationKind::ListingQuestion),
            'orders' => $query->ofKind(ConversationKind::Fulfillment),
            'support' => $query->ofKind(ConversationKind::AdminSeller),
            default => null,
        };

        // `unread` mirrors the nav badge (every unread thread, open or
        // resolved) rather than the status scope every other filter reads
        // through, so the chip and the badge never disagree.
        if ($filter !== 'unread' && $status !== 'all') {
            $query->withStatus(ConversationStatus::from($status));
        }

        $query->with(['seller', 'customer', 'admin', 'listing', 'fulfillment', 'latestMessage'])
            ->withUnreadCountFor($seller);

        return $filter === 'questions' ? $query->unansweredFirst() : $query->orderByDesc('last_message_at');
    }

    /**
     * A thread's list pane, guaranteed to include it. `ListPaneWindow`'s own
     * `mustInclude` only rescues a row that sorts outside the window's SIZE
     * cap — it re-reads the same filtered query, so a filter or status that
     * excludes the thread outright (a direct or bookmarked visit to a
     * resolved thread under the default `status=open`) leaves it out too.
     * This adds one more, unscoped fetch for exactly that case.
     *
     * @return array{items: Collection<int, Model>, total: int}
     */
    private function paneFor(Seller $seller, string $filter, string $status, Conversation $conversation): array
    {
        $window = ListPaneWindow::of($this->conversationsQuery($seller, $filter, $status), $conversation);
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
     * The two chip counts cheap enough to show on every row of the filter
     * bar: how many of the seller's threads are unread, and how many
     * (within the current status scope) are listing questions. `unread`
     * ignores the status scope, the same rule `conversationsQuery` applies
     * to the `unread` filter's own rows, so this chip always equals the nav
     * badge's total.
     *
     * @return array{unread: int, questions: int}
     */
    private function filterCounts(Seller $seller, string $status): array
    {
        $withParticipant = Conversation::query()->withParticipant($seller);
        $scoped = clone $withParticipant;

        if ($status !== 'all') {
            $scoped->withStatus(ConversationStatus::from($status));
        }

        return [
            'unread' => (clone $withParticipant)->unreadOnly($seller)->count(),
            'questions' => (clone $scoped)->ofKind(ConversationKind::ListingQuestion)->count(),
        ];
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
