<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SendMagicLink;
use App\Domain\Auth\ActorType;
use App\Domain\RateLimiting\EmailRateLimitKey;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendMagicLinkRequest;
use App\RateLimiting\RateLimitGate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
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

    public function send(SendMagicLinkRequest $request, SendMagicLink $sendMagicLink, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->checkEach(RateLimitName::MagicLinkRequest, [
                EmailRateLimitKey::for($request->email()),
                'ip:'.$request->ip(),
            ]);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'auth.seller-login');
        }

        $sendMagicLink($request->email(), ActorType::Seller, null, $this->now());

        return redirect()->route('auth.seller.login')->with('sent_to', $request->email());
    }
}
