<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SendMagicLink;
use App\Domain\Auth\ActorType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendAdminMagicLinkRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class AdminLoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.admin-login');
    }

    /**
     * The response reads the same whether or not the address admits an
     * admin, so a probe for who runs the platform learns nothing from it.
     */
    public function send(SendAdminMagicLinkRequest $request, SendMagicLink $sendMagicLink): RedirectResponse
    {
        if ($request->admits()) {
            $sendMagicLink($request->email(), ActorType::Admin, null, $this->now());
        }

        return redirect()->route('auth.admin.login')->with('sent_to', $request->email());
    }
}
