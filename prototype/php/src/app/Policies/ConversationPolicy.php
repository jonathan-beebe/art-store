<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Auth\ActorType;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Seller;
use Illuminate\Auth\Access\Response;

/**
 * A thread has exactly two participants. `view` answers ownership alone and
 * denies as not found, so a thread somebody else is in and a thread that
 * never existed answer the same. `post` is `view` plus standing — the same
 * way `FulfillmentPolicy::ship` is ownership plus state.
 */
final class ConversationPolicy
{
    public function view(Seller|Customer|Admin $actor, Conversation $conversation): Response
    {
        return $this->participant($actor, $conversation);
    }

    public function post(Seller|Customer|Admin $actor, Conversation $conversation): Response
    {
        return $this->whenAllowed(
            $this->participant($actor, $conversation),
            $actor instanceof Customer ? $actor->canShop() : true,
        );
    }

    /**
     * A row that is not the actor's stays a 404; standing that is not ready
     * yet is a plain refusal, since the actor may read the row either way.
     */
    private function whenAllowed(Response $ownership, bool $isReady): Response
    {
        if ($ownership->denied()) {
            return $ownership;
        }

        return $isReady ? Response::allow() : Response::deny();
    }

    private function participant(Seller|Customer|Admin $actor, Conversation $conversation): Response
    {
        $actorType = ActorType::from($actor->getMorphClass());

        return $conversation->participantIdFor($actorType) === $actor->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
