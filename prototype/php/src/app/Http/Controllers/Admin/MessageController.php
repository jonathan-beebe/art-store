<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\PostMessage;
use App\Domain\Auth\ActorType;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Admin\PostMessageRequest;
use App\Models\Conversation;
use App\Support\ListPaneWindow;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class MessageController extends AdminController
{
    public function index(): View
    {
        $window = ListPaneWindow::of($this->conversationsQuery());

        return view('admin.messages.index', [
            'conversations' => $window->items,
            'conversationsTotal' => $window->total,
            'viewer' => ActorType::Admin,
        ]);
    }

    public function show(Conversation $conversation, MarkConversationRead $markRead): View
    {
        $this->authorize('view', $conversation);

        $markRead($conversation, $this->admin(), $this->now());

        // DSGN-006: the show route's list pane is the same inbox the
        // index route opens with, with this thread marked current.
        $window = ListPaneWindow::of($this->conversationsQuery(), $conversation);

        return view('admin.messages.show', [
            ...$this->threadView($conversation),
            'cellConversations' => $window->items,
            'cellConversationsTotal' => $window->total,
        ]);
    }

    /**
     * @return Builder<Conversation>
     */
    private function conversationsQuery(): Builder
    {
        return Conversation::query()
            ->withParticipant($this->admin())
            ->with(['seller', 'customer', 'admin', 'listing', 'fulfillment', 'latestMessage'])
            ->withUnreadCountFor($this->admin())
            ->orderByDesc('last_message_at');
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

            $window = ListPaneWindow::of($this->conversationsQuery(), $conversation);

            return $this->tooManyRequests($exceeded, 'admin.messages.show', [
                ...$this->threadView($conversation),
                'cellConversations' => $window->items,
                'cellConversationsTotal' => $window->total,
            ]);
        }

        $postMessage($conversation, $admin, $request->body(), $this->now());

        return redirect()->route('admin.messages.show', $conversation);
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
            'viewer' => ActorType::Admin,
        ];
    }
}
