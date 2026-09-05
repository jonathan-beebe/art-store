<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Seller;
use App\Models\StoreProfile;
use Illuminate\Auth\Access\Response;

/**
 * A store belongs to one seller. Another seller's store answers "not
 * found", so an id outside a seller's own portal is never confirmed to
 * exist.
 */
final class StoreProfilePolicy
{
    public function view(Seller $seller, StoreProfile $profile): Response
    {
        return $this->ownership($seller, $profile);
    }

    public function update(Seller $seller, StoreProfile $profile): Response
    {
        return $this->ownership($seller, $profile);
    }

    private function ownership(Seller $seller, StoreProfile $profile): Response
    {
        return $profile->seller_id === $seller->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
