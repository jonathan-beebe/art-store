<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\PostMessage;
use App\Domain\Auth\ActorType;
use App\Domain\Messaging\ConversationStatus;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Shop\PostMessageRequest;
use App\Http\Requests\Shop\ShopMessagesIndexRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\RateLimiting\RateLimitGate;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class MessageController extends ShopController
{
    private const CONVERSATIONS_PER_PAGE = 20;

    public function index(ShopMessagesIndexRequest $request): View
    {
        $visitor = $this->visitor();

        $query = Conversation::query()
            ->withParticipant($visitor)
            ->with(['seller', 'customer', 'admin', 'listing', 'fulfillment', 'latestMessage'])
            ->withUnreadCountFor($visitor);

        $filter = $request->filter();

        if ($filter === 'unread') {
            $query->unreadOnly($visitor);
        }

        $status = $request->status();

        // `unread` mirrors the header's own unread count: every unread
        // thread, open or resolved. Every other filter reads through the
        // status scope. The two counts always agree.
        if ($status !== null && $filter !== 'unread') {
            $query->withStatus($status);
        }

        $conversations = $query->orderByDesc('last_message_at')->paginate(self::CONVERSATIONS_PER_PAGE)->withQueryString();

        return view('shop.messages.index', [
            'conversations' => $conversations,
            'viewer' => ActorType::Customer,
            'filter' => $request->filter(),
            'statusValue' => $request->statusValue(),
        ]);
    }

    public function show(Request $request, Conversation $conversation, MarkConversationRead $markRead): View
    {
        $this->authorizeVisitor('view', $conversation);

        $markRead($conversation, $this->visitor(), $this->now());

        return view('shop.messages.show', $this->threadView($conversation, $this->replyTo($request, $conversation)));
    }

    public function store(PostMessageRequest $request, Conversation $conversation, PostMessage $postMessage, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $visitor = $this->knownVisitor();

        try {
            $rateLimit->check(RateLimitName::MessagePost, (string) $visitor->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/spec.md §3: a form that trips comes back on its own page
            // at 429, so the thread the shopper was reading re-renders with
            // the reply still in the box.
            $request->flash();

            return $this->tooManyRequests($exceeded, 'shop.messages.show', $this->threadView($conversation, $request->replyTo()));
        }

        // Read before the post, which may itself clear `resolved_at` — the
        // reopen rule the redirect's flash reports on. `PostMessage` writes
        // that change straight to the database, bypassing this object's
        // own state, so the code re-fetches the conversation afterward to
        // see it.
        $wasResolved = $conversation->status() === ConversationStatus::Resolved;

        $postMessage($conversation, $visitor, $request->body(), $this->now(), $request->replyTo());

        $reopened = $wasResolved && $conversation->fresh()?->status() === ConversationStatus::Open;

        return redirect()->route('shop.messages.show', $conversation)->with($reopened ? ['reopened' => true] : []);
    }

    /**
     * `?reply_to=` names the message a "Reply" link quoted, when it belongs
     * to this thread. A stale or hand-edited id is ignored.
     */
    private function replyTo(Request $request, Conversation $conversation): ?Message
    {
        $replyTo = $request->query('reply_to');

        return is_string($replyTo)
            ? Message::query()->whereKey($replyTo)->where('conversation_id', $conversation->id)->first()
            : null;
    }

    /**
     * The thread page's data. The read mark is not part of it: a trip leaves
     * the world alone, and only `show()` marks the thread read.
     *
     * @return array<string, mixed>
     */
    private function threadView(Conversation $conversation, ?Message $replyTo): array
    {
        $conversation->load([
            'seller', 'customer', 'admin', 'listing', 'fulfillment', 'order', 'resolvedBy',
            'messages' => fn (Relation $query): Relation => $query->orderBy('sent_at')->orderBy('id'),
            'messages.sender', 'messages.replyTo.sender',
        ]);

        return [
            'conversation' => $conversation,
            'viewer' => ActorType::Customer,
            'replyTo' => $replyTo,
        ];
    }
}
