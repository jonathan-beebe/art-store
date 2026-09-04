<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\CustomerIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class SignOutController extends Controller
{
    public function seller(Request $request): RedirectResponse
    {
        Auth::guard('seller')->logout();
        $this->endSession($request);

        return redirect()->route('auth.seller.login');
    }

    public function customer(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        // Dropping the cookie hands the browser a clean anonymous identity on
        // its next storefront request rather than the account it just left.
        CustomerIdentity::forgetCookie();
        $this->endSession($request);

        return redirect()->route('shop.home');
    }

    public function admin(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $this->endSession($request);

        return redirect()->route('auth.admin.login');
    }

    private function endSession(Request $request): void
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
