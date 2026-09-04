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
use App\Models\Conversation;
use App\Models\Message;
use App\Support\ListPaneWindow;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
        $domain = $request->domain();
        $window = ListPaneWindow::of($this->conversationsQuery($domain));

        return view('admin.messages.index', [
            'conversations' => $window->items,
            'conversationsTotal' => $window->total,
            'viewer' => ActorType::Admin,
            'domain' => $domain,
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

        // The list pane reads the same `domain` the inbox row that linked
        // here carried (validated the same way index's own query is, so an
        // unrecognised value still answers 400) — `paneFor` below is what
        // keeps the selected thread in the pane, an oversight thread a
        // narrower domain would otherwise exclude, included.
        $domain = $request->domain();

        $pane = $this->paneFor($domain, $conversation);

        return view('admin.messages.show', [
            ...$this->threadView($conversation, $this->queryReplyTo($request)),
            'cellConversations' => $pane['items'],
            'cellConversationsTotal' => $pane['total'],
            'domain' => $domain,
        ]);
    }

    public function store(PostMessageRequest $request, Conversation $conversation, MessagesQueryRequest $queryRequest, PostMessage $postMessage, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $admin = $this->admin();
        // The composer's own action URL carries the pane's current domain
        // onward (`$paneRouteParams` in the thread component), so reading it
        // here is what keeps a reply's redirect from snapping the pane back
        // to the desk's unscoped default.
        $domain = $queryRequest->domain();

        try {
            $rateLimit->check(RateLimitName::MessagePost, (string) $admin->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/alignment.md §3: a form that trips comes back rather than
            // being replaced by the site's bare 429 page, so the thread the
            // admin was reading re-renders with the reply still in the box.
            $request->flash();

            $pane = $this->paneFor($domain, $conversation);

            return $this->tooManyRequests($exceeded, 'admin.messages.show', [
                ...$this->threadView($conversation, $request->replyToMessageId()),
                'cellConversations' => $pane['items'],
                'cellConversationsTotal' => $pane['total'],
                'domain' => $domain,
            ]);
        }

        $postMessage($conversation, $admin, $request->body(), $this->now(), $this->replyTo($conversation, $request->replyToMessageId()));

        return redirect()->route('admin.messages.show', ['conversation' => $conversation, 'domain' => $domain]);
    }

    /**
     * The inbox query for a given domain — docs/messaging.md § "Inbox
     * domains". Every status is listed; only the domain narrows it.
     *
     * @return Builder<Conversation>
     */
    private function conversationsQuery(string $domain): Builder
    {
        $admin = $this->admin();

        $eloquent = Conversation::query()
            ->with(['seller', 'customer', 'admin', 'listing', 'fulfillment', 'latestMessage.sender'])
            ->withUnreadCountFor($admin);

        $kinds = $this->domainKinds($domain);

        if ($kinds !== null) {
            $eloquent->ofKind(...$kinds);
        }

        return $eloquent->orderByDesc('last_message_at');
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
    private function paneFor(string $domain, Conversation $conversation): array
    {
        $admin = $this->admin();
        $window = ListPaneWindow::of($this->conversationsQuery($domain), $conversation);
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
