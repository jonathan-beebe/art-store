<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Auth\ActorType;
use App\Domain\Messaging\ConversationStatus;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Seller;
use Illuminate\Auth\Access\Response;

/**
 * The desk sees every thread; a seller or a customer sees only their own,
 * denied as not found otherwise, so a thread somebody else is in and a
 * thread that never existed answer the same. `post` is `view` plus standing
 * — the same way `FulfillmentPolicy::ship` is ownership plus state.
 */
final class ConversationPolicy
{
    public function view(Seller|Customer|Admin $actor, Conversation $conversation): Response
    {
        return $actor instanceof Admin ? Response::allow() : $this->participant($actor, $conversation);
    }

    /**
     * The desk never posts into a seller ↔ customer thread — the two-sides
     * invariant is what keeps `read_at` and the notification recipient
     * unambiguous — so an admin's standing to post is the kind alone,
     * independent of `admin_id`, which only ever names who answered first.
     */
    public function post(Seller|Customer|Admin $actor, Conversation $conversation): Response
    {
        if ($actor instanceof Admin) {
            return $conversation->kind->admits(ActorType::Admin) ? Response::allow() : Response::deny();
        }

        return $this->whenAllowed(
            $this->participant($actor, $conversation),
            $actor instanceof Customer ? $actor->canShop() : true,
        );
    }

    public function resolve(Seller|Customer|Admin $actor, Conversation $conversation): Response
    {
        return $this->transition($actor, $conversation, ConversationStatus::Resolved);
    }

    public function reopen(Seller|Customer|Admin $actor, Conversation $conversation): Response
    {
        return $this->transition($actor, $conversation, ConversationStatus::Open);
    }

    /**
     * Moving to a status is allowed only for the side the kind lets resolve,
     * and only when the thread does not already hold that status.
     */
    private function transition(Seller|Customer|Admin $actor, Conversation $conversation, ConversationStatus $target): Response
    {
        $view = $this->view($actor, $conversation);

        if ($view->denied()) {
            return $view;
        }

        $actorType = ActorType::from($actor->getMorphClass());

        if (! $conversation->kind->resolvableBy($actorType)) {
            return Response::deny();
        }

        return ConversationStatus::of($conversation->resolved_at) === $target ? Response::deny() : Response::allow();
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
