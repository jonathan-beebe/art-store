<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\ConversationSubject;
use App\Models\Conversation;
use DateTimeImmutable;

final readonly class OpenConversation
{
    public function __invoke(ConversationSubject $subject, DateTimeImmutable $now): Conversation
    {
        return Conversation::openFor($subject, $now);
    }
}
