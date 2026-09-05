<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\ApiKeys\MintApiKey;
use App\Http\Requests\Admin\MintApiKeyRequest;
use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The signed-in admin's MCP api keys (docs/spec.md §5.1, app/docs/mcp.md
 * § "Keys"): the list of their own keys, newest first, and the form that
 * mints one. A minted key's plaintext rides the session once, to the
 * page that follows the redirect, and nowhere after.
 */
final class ApiKeyController extends AdminController
{
    public const string MINTED_KEY = 'minted_api_key';

    public function index(): View
    {
        $admin = $this->admin();

        return view('admin.api-keys.index', [
            'keys' => ApiKey::query()
                ->where('admin_id', $admin->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
            'mintedKey' => session(self::MINTED_KEY),
        ]);
    }

    public function store(MintApiKeyRequest $request, MintApiKey $mintApiKey): RedirectResponse
    {
        $minted = $mintApiKey($this->admin(), $request->name());

        return redirect()->route('admin.api-keys.index')
            ->with('status', "Key {$minted->key->name} minted. Copy it now — it is shown once.")
            ->with(self::MINTED_KEY, $minted->plaintext);
    }
}
