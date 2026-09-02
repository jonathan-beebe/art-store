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

    public function show(Conversation $conversation, MarkConversationRead $markRead, Request $request): View
    {
        $this->authorize('view', $conversation);

        $seller = $this->seller();
        $markRead($conversation, $seller, $this->now());

        // DSGN-006: the show route's list pane is the same default inbox
        // the index route opens with, with this thread marked current — the
        // chips it renders link to the index route's own filtered views
        // rather than tracking a filter of their own here.
        $window = ListPaneWindow::of(
            $this->conversationsQuery($seller, MessagesQueryRequest::DEFAULT_FILTER, MessagesQueryRequest::DEFAULT_STATUS),
            $conversation,
        );

        return view('seller.messages.show', [
            ...$this->threadView($conversation, $this->replyToId($request)),
            'cellConversations' => $window->items,
            'cellConversationsTotal' => $window->total,
            'filter' => MessagesQueryRequest::DEFAULT_FILTER,
            'status' => MessagesQueryRequest::DEFAULT_STATUS,
            'filterCounts' => $this->filterCounts($seller, MessagesQueryRequest::DEFAULT_STATUS),
        ]);
    }

    public function store(PostMessageRequest $request, Conversation $conversation, PostMessage $postMessage, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $seller = $this->seller();

        try {
            $rateLimit->check(RateLimitName::MessagePost, (string) $seller->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/alignment.md §3: a form that trips comes back rather than
            // being replaced by the site's bare 429 page, so the thread the
            // seller was reading re-renders with the reply still in the box.
            $request->flash();

            $window = ListPaneWindow::of($this->conversationsQuery($seller, MessagesQueryRequest::DEFAULT_FILTER, MessagesQueryRequest::DEFAULT_STATUS), $conversation);

            return $this->tooManyRequests($exceeded, 'seller.messages.show', [
                ...$this->threadView($conversation, $this->replyToId($request)),
                'cellConversations' => $window->items,
                'cellConversationsTotal' => $window->total,
                'filter' => MessagesQueryRequest::DEFAULT_FILTER,
                'status' => MessagesQueryRequest::DEFAULT_STATUS,
                'filterCounts' => $this->filterCounts($seller, MessagesQueryRequest::DEFAULT_STATUS),
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

        if ($status !== 'all') {
            $query->withStatus(ConversationStatus::from($status));
        }

        $query->with(['seller', 'customer', 'admin', 'listing', 'fulfillment', 'latestMessage'])
            ->withUnreadCountFor($seller);

        return $filter === 'questions' ? $query->unansweredFirst() : $query->orderByDesc('last_message_at');
    }

    /**
     * The two chip counts cheap enough to show on every row of the filter
     * bar: how many of the seller's threads (within the current status
     * scope) are unread, and how many are listing questions.
     *
     * @return array{unread: int, questions: int}
     */
    private function filterCounts(Seller $seller, string $status): array
    {
        $base = Conversation::query()->withParticipant($seller);

        if ($status !== 'all') {
            $base->withStatus(ConversationStatus::from($status));
        }

        return [
            'unread' => (clone $base)->unreadOnly($seller)->count(),
            'questions' => (clone $base)->ofKind(ConversationKind::ListingQuestion)->count(),
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
