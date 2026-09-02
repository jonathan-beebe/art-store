<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Messaging\MessageBody;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class PostMessageRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('post', $this->conversation());
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
     * The message a "Reply" link named, or null for a plain reply and for a
     * `reply_to_message_id` naming no message of this thread — the composer
     * reads a stray or cross-thread id as if it had named none at all,
     * rather than refusing the reply over it.
     */
    public function replyTo(): ?Message
    {
        $id = $this->string('reply_to_message_id')->toString();

        if ($id === '') {
            return null;
        }

        $message = Message::find($id);

        return $message !== null && $message->conversation_id === $this->conversation()->id ? $message : null;
    }

    private function conversation(): Conversation
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof Conversation
            ? $conversation
            : throw new RuntimeException('The message route binds a conversation.');
    }
}
