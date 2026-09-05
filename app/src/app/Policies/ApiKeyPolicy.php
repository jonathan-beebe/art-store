<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\ApiKey;
use Illuminate\Auth\Access\Response;

/**
 * An api key is its admin's alone (docs/spec.md §5.1): another admin's
 * key answers 404, so a key id outside one's own is never confirmed to
 * exist.
 */
final class ApiKeyPolicy
{
    public function revoke(Admin $admin, ApiKey $key): Response
    {
        return $key->admin_id === $admin->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
