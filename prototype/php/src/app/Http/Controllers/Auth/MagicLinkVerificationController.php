<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SignInAdmin;
use App\Actions\Auth\SignInCustomer;
use App\Actions\Auth\SignInSeller;
use App\Actions\Customers\ResolveCustomerFromCookie;
use App\Domain\Auth\ActorType;
use App\Domain\Auth\LocalRedirect;
use App\Domain\Auth\MagicLinkStatus;
use App\Http\Controllers\Controller;
use App\Logging\StoryEvent;
use App\Models\MagicLink;
use App\Support\CustomerIdentity;
use App\Support\Story;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class MagicLinkVerificationController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        SignInSeller $signInSeller,
        SignInCustomer $signInCustomer,
        SignInAdmin $signInAdmin,
        ResolveCustomerFromCookie $resolveFromCookie,
    ): RedirectResponse {
        // Neither the token nor the address it was issued to reaches a line;
        // the link row's own id is what names it.
        $story = Story::for(StoryEvent::MagicLinkConsume)->will('verifying a sign-in link');

        $link = MagicLink::forToken($token)->first();

        if ($link === null) {
            $story->refused('that sign-in link names no row');

            return $this->refuse(ActorType::Customer, 'That sign-in link is not valid. Ask for a new one.');
        }

        $now = $this->now();

        $refusal = match ($link->statusAt($now)) {
            MagicLinkStatus::Usable => null,
            MagicLinkStatus::Expired => 'That sign-in link has expired. Ask for a new one.',
            MagicLinkStatus::Consumed => 'That sign-in link has already been used. Ask for a new one.',
        };

        if ($refusal !== null) {
            $story->refused($refusal, [
                'magic_link_id' => $link->id,
                'actor_type' => $link->actor_type->value,
            ]);

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
            ActorType::Admin => $signInAdmin($link->email, $now),
        };

        $request->session()->regenerate();

        $story->did('signed the actor in from the link', [
            'magic_link_id' => $link->id,
            'actor_type' => $link->actor_type->value,
        ]);

        return redirect()->to(LocalRedirect::resolve(
            $link->redirect_to,
            $link->actor_type,
            route($link->actor_type->homeRouteName()),
            url('/'),
        ));
    }

    private function refuse(ActorType $actorType, string $message): RedirectResponse
    {
        return redirect()->route($actorType->loginRouteName())->with('error', $message);
    }
}
