<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SignInCustomer;
use App\Actions\Auth\SignInSeller;
use App\Actions\Customers\ResolveCustomerFromCookie;
use App\Domain\Auth\ActorType;
use App\Domain\Auth\LocalRedirect;
use App\Domain\Auth\MagicLinkStatus;
use App\Http\Controllers\Controller;
use App\Models\MagicLink;
use App\Support\CustomerIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class MagicLinkVerificationController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        SignInSeller $signInSeller,
        SignInCustomer $signInCustomer,
        ResolveCustomerFromCookie $resolveFromCookie,
    ): RedirectResponse {
        $link = MagicLink::forToken($token)->first();

        if ($link === null) {
            return $this->refuse(ActorType::Customer, 'That sign-in link is not valid. Ask for a new one.');
        }

        $now = $this->now();

        $refusal = match ($link->statusAt($now)) {
            MagicLinkStatus::Usable => null,
            MagicLinkStatus::Expired => 'That sign-in link has expired. Ask for a new one.',
            MagicLinkStatus::Consumed => 'That sign-in link has already been used. Ask for a new one.',
        };

        if ($refusal !== null) {
            return $this->refuse($link->actor_type, $refusal);
        }

        $link->consume($now);

        match ($link->actor_type) {
            ActorType::Seller => $signInSeller($link->email, $now),
            ActorType::Customer => $signInCustomer(
                $link->email,
                $resolveFromCookie(CustomerIdentity::cookieValue($request)),
                $now,
            ),
        };

        $request->session()->regenerate();

        return redirect()->to($this->destinationFor($link));
    }

    /**
     * A customer's `redirect_to` still has to stay off the seller portal even
     * once it has passed {@see LocalRedirect} — a customer link carries no
     * seller session, so following it there would only land on a login wall.
     */
    private function destinationFor(MagicLink $link): string
    {
        $home = route($link->actor_type->homeRouteName());
        $destination = LocalRedirect::resolve($link->redirect_to, $home, url('/'));

        if ($link->actor_type === ActorType::Customer && str_starts_with((string) parse_url($destination, PHP_URL_PATH), '/seller')) {
            return $home;
        }

        return $destination;
    }

    private function refuse(ActorType $actorType, string $message): RedirectResponse
    {
        return redirect()->route($actorType->loginRouteName())->with('error', $message);
    }
}
