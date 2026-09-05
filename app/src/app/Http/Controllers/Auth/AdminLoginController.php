<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SendMagicLink;
use App\Domain\Auth\ActorType;
use App\Domain\RateLimiting\EmailRateLimitKey;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendAdminMagicLinkRequest;
use App\Models\Admin;
use App\RateLimiting\RateLimitGate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
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
     * The rate-limit check runs ahead of that read for the same reason: an
     * address that never admits an admin must spend the same budget as one
     * that does, or counting requests would leak what checking the address
     * does not. Under session delivery an unadmitted address still gets a
     * debug notice, since that delivery has no mailbox behind it to fall
     * back on.
     */
    public function send(SendAdminMagicLinkRequest $request, SendMagicLink $sendMagicLink, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->checkEach(RateLimitName::MagicLinkRequest, [
                EmailRateLimitKey::for($request->email()),
                'ip:'.$request->ip(),
            ]);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'auth.admin-login');
        }

        $redirect = redirect()->route('auth.admin.login')->with('sent_to', $request->email());

        if (Admin::admitsEmail($request->email())) {
            $sendMagicLink($request->email(), ActorType::Admin, null, $this->now());

            return $redirect;
        }

        if (config('magic_links.delivery') === 'session') {
            $redirect->with(
                'debug_notice',
                "No admin account exists for {$request->email()}. No sign-in link was issued.",
            );
        }

        return $redirect;
    }
}
