<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Seller\MessageDomain;
use Illuminate\Validation\Rule;

/**
 * The seller inbox's `?domain=` (docs/messaging.md § "Inbox domains") and
 * the thread page's `?reply_to=`.
 */
final class MessagesQueryRequest extends SellerQueryRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'domain' => ['nullable', Rule::enum(MessageDomain::class)],
            'reply_to' => ['nullable', 'string'],
        ];
    }

    /** The current domain tab, defaulted when absent. */
    public function domain(): MessageDomain
    {
        return $this->enum('domain', MessageDomain::class) ?? MessageDomain::default();
    }

    /** The message the composer is replying to, named on the URL. */
    public function replyTo(): ?string
    {
        return $this->stringOrNull('reply_to');
    }
}
