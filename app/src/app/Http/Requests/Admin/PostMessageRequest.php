<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Messaging\MessageBody;
use App\Models\Conversation;
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
     * The message a reply quotes, read off the hidden field the composer's
     * "Reply" link rode in on. An id belonging to another thread is left
     * for the controller to ignore; this class leaves it unvalidated.
     * `?reply_to` on the thread's GET route is ignored the same way.
     */
    public function replyToMessageId(): ?string
    {
        $value = $this->string('reply_to_message_id')->toString();

        return $value === '' ? null : $value;
    }

    private function conversation(): Conversation
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof Conversation
            ? $conversation
            : throw new RuntimeException('The message route binds a conversation.');
    }
}
