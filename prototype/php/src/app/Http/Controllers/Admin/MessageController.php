<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\PostMessage;
use App\Domain\Auth\ActorType;
use App\Domain\Messaging\ConversationKind;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Admin\MessagesQueryRequest;
use App\Http\Requests\Admin\PostMessageRequest;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Message;
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
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class MessageController extends AdminController
{
    /** The Type checkbox → kind mapping (docs/messaging.md § "Inbox
     * filters and the seller's queue"). Support is both desk kinds: the
     * admin's "Support" type isn't split by counterpart. */
    private const array TYPE_KINDS = [
        'questions' => [ConversationKind::ListingQuestion],
        'orders' => [ConversationKind::Fulfillment],
        'support' => [ConversationKind::AdminSeller, ConversationKind::AdminCustomer],
    ];

    public function index(MessagesQueryRequest $request): View
    {
        $query = $request->inboxQuery();
        $window = ListPaneWindow::of($this->conversationsQuery($query));

        return view('admin.messages.index', [
            'conversations' => $window->items,
            'conversationsTotal' => $window->total,
            'viewer' => ActorType::Admin,
            'query' => $query,
            'needsReplyCount' => $this->needsReplyCount($query->domain),
        ]);
    }

    public function show(Conversation $conversation, MessagesQueryRequest $request, MarkConversationRead $markRead): View
    {
        $this->authorize('view', $conversation);

        // docs/messaging.md § "Who may read, post, and resolve": an admin
        // reading an oversight thread (seller <-> customer) does not mark it
        // read — `post` is the same standing check that gates the composer,
        // so a thread with no composer never has its unread state touched.
        if (Gate::allows('post', $conversation)) {
            $markRead($conversation, $this->admin(), $this->now());
        }

        // The list pane reads the same `domain`/`type`/`status` the inbox
        // row that linked here carried (validated the same way index's own
        // query is, so an unrecognised value still answers 400), defaulting
        // to every status rather than index's Open-only — `paneFor` below is
        // what keeps the selected thread in the pane, an oversight or
        // resolved thread a narrow selection would otherwise exclude,
        // included.
        $query = $request->paneQuery();

        $pane = $this->paneFor($query, $conversation);

        return view('admin.messages.show', [
            ...$this->threadView($conversation, $this->queryReplyTo($request)),
            'cellConversations' => $pane['items'],
            'cellConversationsTotal' => $pane['total'],
            'query' => $query,
            'needsReplyCount' => $this->needsReplyCount($query->domain),
        ]);
    }

    public function store(PostMessageRequest $request, Conversation $conversation, MessagesQueryRequest $queryRequest, PostMessage $postMessage, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $admin = $this->admin();
        // The composer's own action URL carries the pane's current
        // selection onward (`$paneRouteParams` in the thread component), so
        // reading it here is what keeps a reply's redirect from snapping the
        // pane back to the desk's unscoped default.
        $query = $queryRequest->paneQuery();

        try {
            $rateLimit->check(RateLimitName::MessagePost, (string) $admin->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/alignment.md §3: a form that trips comes back rather than
            // being replaced by the site's bare 429 page, so the thread the
            // admin was reading re-renders with the reply still in the box.
            $request->flash();

            $pane = $this->paneFor($query, $conversation);

            return $this->tooManyRequests($exceeded, 'admin.messages.show', [
                ...$this->threadView($conversation, $request->replyToMessageId()),
                'cellConversations' => $pane['items'],
                'cellConversationsTotal' => $pane['total'],
                'query' => $query,
                'needsReplyCount' => $this->needsReplyCount($query->domain),
            ]);
        }

        $postMessage($conversation, $admin, $request->body(), $this->now(), $this->replyTo($conversation, $request->replyToMessageId()));

        return redirect()->route('admin.messages.show', ['conversation' => $conversation, ...$query->toRouteParams()]);
    }

    /**
     * The inbox query for a given domain/type/status selection —
     * docs/messaging.md § "Inbox filters and the seller's queue".
     *
     * @return Builder<Conversation>
     */
    private function conversationsQuery(InboxQuery $query): Builder
    {
        $admin = $this->admin();

        $eloquent = Conversation::query()
            ->with(['seller', 'customer', 'admin', 'listing', 'fulfillment', 'latestMessage.sender'])
            ->withUnreadCountFor($admin);

        $kinds = InboxQuery::intersectKinds($this->domainKinds($query->domain), $this->typeKinds($query->types));

        if ($kinds !== null) {
            $eloquent->ofKind(...$kinds);
        }

        $this->applyStatuses($eloquent, $admin, $query->statuses);

        return $eloquent->unreadFirst();
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
    private function paneFor(InboxQuery $query, Conversation $conversation): array
    {
        $admin = $this->admin();
        $window = ListPaneWindow::of($this->conversationsQuery($query), $conversation);
        $items = $window->items;

        if (! $items->contains('id', '=', $conversation->id)) {
            $items = $items->prepend(
                Conversation::query()
                    ->with(['seller', 'customer', 'admin', 'listing', 'fulfillment', 'latestMessage.sender'])
                    ->withUnreadCountFor($admin)
                    ->whereKey($conversation->id)
                    ->firstOrFail(),
            );
        }

        return ['items' => $items, 'total' => $window->total];
    }

    /**
     * The domain tab's kind restriction, or null for `all` — the desk's
     * threads and every oversight thread, every kind there is.
     *
     * @return list<ConversationKind>|null
     */
    private function domainKinds(string $domain): ?array
    {
        return match ($domain) {
            'sellers' => [ConversationKind::AdminSeller],
            'customers' => [ConversationKind::AdminCustomer],
            default => null,
        };
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
     * they cover every conversation. A desk thread no admin has read yet
     * passes whatever the group says: a seller or customer who replies to a
     * resolved thread reopens it in the desk's eyes, and the nav badge
     * counts it, so the inbox lists it under the default Open-only view too.
     * Oversight threads are never unread for the desk (the row's own dot
     * follows the same rule), so the clause is scoped to the desk kinds.
     *
     * @param  Builder<Conversation>  $query
     * @param  list<string>  $statuses
     */
    private function applyStatuses(Builder $query, Admin $admin, array $statuses): void
    {
        if (in_array('open', $statuses, true) && in_array('resolved', $statuses, true)) {
            return;
        }

        $query->where(function (Builder $scoped) use ($admin, $statuses): void {
            foreach ($statuses as $status) {
                match ($status) {
                    'open' => $scoped->orWhereNull('resolved_at'),
                    'resolved' => $scoped->orWhereNotNull('resolved_at'),
                    'needs-reply' => $scoped->orWhere(fn (Builder $needsReply) => $needsReply->needsReply()),
                    default => null, // MessagesQueryRequest already refused anything else.
                };
            }

            $scoped->orWhere(fn (Builder $unread) => $unread->withParticipant($admin)->unreadOnly($admin));
        });
    }

    /** The desk's work-queue count for the Needs reply row of the popover,
     * scoped to the current domain the same way the list itself is. */
    private function needsReplyCount(string $domain): int
    {
        $query = Conversation::query()->needsReply();
        $kinds = $this->domainKinds($domain);

        if ($kinds !== null) {
            $query->ofKind(...$kinds);
        }

        return $query->count();
    }

    /** `?reply_to` on the thread's GET route — a blank or absent value is
     * no reply target, not an id to look up. */
    private function queryReplyTo(Request $request): ?string
    {
        $value = $request->string('reply_to')->toString();

        return $value === '' ? null : $value;
    }

    /**
     * The message a reply quotes, from either the "Reply" link's `?reply_to`
     * query parameter or the composer's hidden field — an id naming a
     * message from another thread, or no message at all, is ignored rather
     * than refused (docs/messaging.md § "Replying to a message").
     */
    private function replyTo(Conversation $conversation, ?string $messageId): ?Message
    {
        return $messageId === null ? null : $conversation->messages()->whereKey($messageId)->first();
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
            'messages.sender', 'messages.replyTo.sender',
        ]);

        return [
            'conversation' => $conversation,
            'viewer' => ActorType::Admin,
            'replyTo' => $this->replyTo($conversation, $replyToId),
        ];
    }
}
