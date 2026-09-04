<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Seller;
use Illuminate\Auth\Access\Response;

/**
 * A seller's own screens read nothing bound by a route parameter, so there
 * is no other seller's row to deny — this states the same ownership rule
 * every other seller policy states, for the one page that would otherwise
 * state none of it.
 */
final class SellerPolicy
{
    public function view(Seller $actor, Seller $subject): Response
    {
        return $actor->id === $subject->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
