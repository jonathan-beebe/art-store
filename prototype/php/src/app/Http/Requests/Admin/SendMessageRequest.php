<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Messaging\MessageBody;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Starts or continues a support thread from a seller's or a customer's
 * detail page. Both routes share this request: the input contract is the
 * same body limit every other write reads from the domain, and there is no
 * conversation yet to authorize against the way a reply's request does — the
 * admin guard already settled who may reach the route.
 */
final class SendMessageRequest extends FormRequest
{
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
}
