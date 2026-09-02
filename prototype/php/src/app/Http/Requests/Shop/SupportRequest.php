<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Domain\Messaging\MessageBody;
use App\Domain\Messaging\ThreadTitle;

/**
 * `POST /support`'s form: what the thread is about, the opening message,
 * and — optionally — which of the visitor's own orders it concerns.
 */
final class SupportRequest extends ShopRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:'.ThreadTitle::MAX_LENGTH],
            'body' => ['required', 'string', 'max:'.MessageBody::MAX_LENGTH],
            'order_id' => ['nullable', 'string'],
        ];
    }

    public function title(): ThreadTitle
    {
        return ThreadTitle::of($this->string('subject')->toString());
    }

    public function body(): MessageBody
    {
        return MessageBody::of($this->string('body')->toString());
    }

    /**
     * The order this thread is raised over, when the visitor named one that
     * is actually theirs — an order naming somebody else's, or naming
     * nothing at all, is ignored rather than refused.
     */
    public function orderId(): ?string
    {
        $orderId = $this->stringOrNull('order_id');

        return $orderId !== null && $this->visitor()->orders()->whereKey($orderId)->exists()
            ? $orderId
            : null;
    }

    private function stringOrNull(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
