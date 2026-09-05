<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;

/**
 * Revokes one of the signed-in admin's keys; another admin's key answers
 * 404 through `ApiKeyPolicy`. Revoking twice is a no-op with the same
 * message, since the row keeps its first `revoked_at`.
 */
final class RevokeApiKeyController extends Controller
{
    public function __invoke(ApiKey $apiKey): RedirectResponse
    {
        $this->authorize('revoke', $apiKey);

        $apiKey->revoke($this->now());

        return redirect()->route('admin.api-keys.index')->with('status', "Key {$apiKey->name} revoked.");
    }
}
