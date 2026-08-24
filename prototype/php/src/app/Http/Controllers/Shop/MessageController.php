<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\PostMessage;
use App\Domain\Auth\ActorType;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Shop\PostMessageRequest;
use App\Models\Conversation;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
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

        $conversation->load([
            'seller', 'customer', 'admin', 'listing', 'fulfillment',
            'messages' => fn (Relation $query): Relation => $query->orderBy('sent_at')->orderBy('id'),
            'messages.sender',
        ]);

        return view('shop.messages.show', [
            'conversation' => $conversation,
            'viewer' => ActorType::Customer,
        ]);
    }

    public function store(PostMessageRequest $request, Conversation $conversation, PostMessage $postMessage, RateLimitGate $rateLimit): RedirectResponse
    {
        $visitor = $this->visitor();
        $rateLimit->check(RateLimitName::MessagePost, (string) $visitor->id);

        $postMessage($conversation, $visitor, $request->body(), $this->now());

        return redirect()->route('shop.messages.show', $conversation);
    }
}
