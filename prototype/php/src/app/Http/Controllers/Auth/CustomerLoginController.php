<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SendMagicLink;
use App\Domain\Auth\ActorType;
use App\Domain\Auth\LocalRedirect;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class CustomerLoginController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('shop.account');
        }

        return view('auth.customer-login', [
            'redirectTo' => LocalRedirect::keepIfLocal($request->query('redirect_to'), url('/')),
        ]);
    }

    public function send(Request $request, SendMagicLink $sendMagicLink): RedirectResponse
    {
        $submitted = $request->validate([
            'email' => ['required', 'email'],
            'redirect_to' => ['nullable', 'string'],
        ]);

        $sendMagicLink(
            $submitted['email'],
            ActorType::Customer,
            LocalRedirect::keepIfLocal($submitted['redirect_to'] ?? null, url('/')),
        );

        return redirect()->route('auth.customer.login')->with('sent_to', $submitted['email']);
    }
}
