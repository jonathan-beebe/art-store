<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SendMagicLink;
use App\Domain\Auth\ActorType;
use App\Domain\Auth\EmailNormalizer;
use App\Domain\Auth\LocalRedirect;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendMagicLinkRequest;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function send(SendMagicLinkRequest $request, SendMagicLink $sendMagicLink, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->checkEach(RateLimitName::MagicLinkRequest, [
                'email:'.hash('sha256', EmailNormalizer::normalize($request->email())),
                'ip:'.$request->ip(),
            ]);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'auth.customer-login', ['redirectTo' => $request->redirectTo()]);
        }

        $sendMagicLink($request->email(), ActorType::Customer, $request->redirectTo(), $this->now());

        return redirect()->route('auth.customer.login')->with('sent_to', $request->email());
    }
}
