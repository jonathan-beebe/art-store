<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SendMagicLink;
use App\Domain\Auth\ActorType;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class SellerLoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::guard('seller')->check()) {
            return redirect()->route('seller.dashboard');
        }

        return view('auth.seller-login');
    }

    public function send(Request $request, SendMagicLink $sendMagicLink): RedirectResponse
    {
        $submitted = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $sendMagicLink($submitted['email'], ActorType::Seller);

        return redirect()->route('auth.seller.login')->with('sent_to', $submitted['email']);
    }
}
