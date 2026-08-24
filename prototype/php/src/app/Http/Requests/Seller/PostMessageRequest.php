<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

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
        ];
    }

    public function body(): MessageBody
    {
        return MessageBody::of($this->string('body')->toString());
    }

    private function conversation(): Conversation
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof Conversation
            ? $conversation
            : throw new RuntimeException('The message route binds a conversation.');
    }
}
