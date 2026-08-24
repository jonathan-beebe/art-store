<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Seller;
use DateTimeImmutable;

final readonly class MarkConversationRead
{
    public function __invoke(Conversation $conversation, Seller|Customer|Admin $reader, DateTimeImmutable $now): void
    {
        $conversation->messages()->unreadBy($reader)->update(['read_at' => $now]);
    }
}
