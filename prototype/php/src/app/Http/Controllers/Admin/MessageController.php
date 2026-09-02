<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\PostMessage;
use App\Domain\Auth\ActorType;
use App\Domain\Messaging\ConversationKind;
use App\Domain\Messaging\ConversationStatus;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Admin\MessagesQueryRequest;
use App\Http\Requests\Admin\PostMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\ListPaneWindow;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class MessageController extends AdminController
{
    public function index(MessagesQueryRequest $request): View
    {
        $filter = $request->filter();
        $status = $request->status();
        $window = ListPaneWindow::of($this->conversationsQuery($filter, $status));

        return view('admin.messages.index', [
            'conversations' => $window->items,
            'conversationsTotal' => $window->total,
            'viewer' => ActorType::Admin,
            'filter' => $filter,
            'status' => $status,
        ]);
    }

    public function show(Conversation $conversation, Request $request, MarkConversationRead $markRead): View
    {
        $this->authorize('view', $conversation);

        // docs/messaging.md § "Who may read, post, and resolve": an admin
        // reading an oversight thread (seller <-> customer) does not mark it
        // read — `post` is the same standing check that gates the composer,
        // so a thread with no composer never has its unread state touched.
        if (Gate::allows('post', $conversation)) {
            $markRead($conversation, $this->admin(), $this->now());
        }

        // DSGN-006: the show route's list pane is a list of every thread,
        // unfiltered — a show visit carries no filter of its own, and the
        // desk's own needs-reply queue would leave an oversight thread (or
        // any resolved one) with no place in its own pane at all.
        $window = ListPaneWindow::of($this->conversationsQuery('all', 'all'), $conversation);

        return view('admin.messages.show', [
            ...$this->threadView($conversation, $this->queryReplyTo($request)),
            'cellConversations' => $window->items,
            'cellConversationsTotal' => $window->total,
        ]);
    }

    public function store(PostMessageRequest $request, Conversation $conversation, PostMessage $postMessage, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $admin = $this->admin();

        try {
            $rateLimit->check(RateLimitName::MessagePost, (string) $admin->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/alignment.md §3: a form that trips comes back rather than
            // being replaced by the site's bare 429 page, so the thread the
            // admin was reading re-renders with the reply still in the box.
            $request->flash();

            $window = ListPaneWindow::of($this->conversationsQuery('all', 'all'), $conversation);

            return $this->tooManyRequests($exceeded, 'admin.messages.show', [
                ...$this->threadView($conversation, $request->replyToMessageId()),
                'cellConversations' => $window->items,
                'cellConversationsTotal' => $window->total,
            ]);
        }

        $postMessage($conversation, $admin, $request->body(), $this->now(), $this->replyTo($conversation, $request->replyToMessageId()));

        return redirect()->route('admin.messages.show', $conversation);
    }

    /**
     * The inbox query for a given filter and status — docs/messaging.md §
     * "Inbox filters and the seller's queue". `needs-reply` already reads as
     * open desk threads waiting on a reply, so the status parameter is
     * folded into every other filter rather than into it.
     *
     * @return Builder<Conversation>
     */
    private function conversationsQuery(string $filter, string $status): Builder
    {
        $admin = $this->admin();

        $query = Conversation::query()
            ->with(['seller', 'customer', 'admin', 'listing', 'fulfillment', 'latestMessage.sender'])
            ->withUnreadCountFor($admin);

        match ($filter) {
            'needs-reply' => $query->needsReply(),
            'sellers' => $query->withParticipant($admin)->ofKind(ConversationKind::AdminSeller),
            'customers' => $query->withParticipant($admin)->ofKind(ConversationKind::AdminCustomer),
            'orders' => $query->forOversight()->ofKind(ConversationKind::Fulfillment),
            'questions' => $query->forOversight()->ofKind(ConversationKind::ListingQuestion),
            default => null, // 'all': the desk's threads and every oversight thread, every kind there is.
        };

        if ($filter !== 'needs-reply') {
            match ($status) {
                'open' => $query->withStatus(ConversationStatus::Open),
                'resolved' => $query->withStatus(ConversationStatus::Resolved),
                default => null, // 'all': no status predicate.
            };
        }

        return $query->orderByDesc('last_message_at');
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
