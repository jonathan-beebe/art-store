<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Domain\Messaging\MessageBody;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class PostMessageRequest extends ShopRequest
{
    public function authorize(): Response
    {
        return Gate::forUser($this->visitor())->inspect('post', $this->conversation());
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.MessageBody::MAX_LENGTH],
            'reply_to_message_id' => ['nullable', 'string'],
        ];
    }

    public function body(): MessageBody
    {
        return MessageBody::of($this->string('body')->toString());
    }

    /**
     * The message a "Reply" link quoted, when the hidden field names one
     * that actually belongs to this thread — a hand-rolled id naming
     * another thread's message, or nothing at all, is ignored rather than
     * refused.
     */
    public function replyTo(): ?Message
    {
        $replyToId = $this->string('reply_to_message_id')->toString();

        if ($replyToId === '') {
            return null;
        }

        return Message::query()->whereKey($replyToId)->where('conversation_id', $this->conversation()->id)->first();
    }

    private function conversation(): Conversation
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof Conversation
            ? $conversation
            : throw new RuntimeException('The message route binds a conversation.');
    }
}
