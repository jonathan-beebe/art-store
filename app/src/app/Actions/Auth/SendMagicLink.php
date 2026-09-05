<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Domain\Auth\ActorType;
use App\Domain\Auth\EmailNormalizer;
use App\Domain\Auth\MagicLinkToken;
use App\Logging\StoryEvent;
use App\Models\MagicLink;
use App\Notifications\MagicLinkIssued;
use App\Support\Story;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Facades\Notification;

final readonly class SendMagicLink
{
    private const TOKEN_BYTES = 40;

    public function __invoke(string $email, ActorType $actorType, ?string $redirectTo, DateTimeImmutable $now): void
    {
        // The address and the token are both credentials, so the story names
        // the row rather than either of them.
        Story::for(StoryEvent::MagicLinkRequest)->tell('issuing a sign-in link', [
            'actor_type' => $actorType->value,
        ], function (Story $story) use ($email, $actorType, $redirectTo, $now): void {
            $token = bin2hex(random_bytes(self::TOKEN_BYTES));
            $address = EmailNormalizer::normalize($email);
            $expiryMinutes = (int) config('magic_links.expiry_minutes');

            $link = MagicLink::create([
                'token_hash' => MagicLinkToken::hash($token),
                'email' => $address,
                'actor_type' => $actorType,
                'redirect_to' => $redirectTo,
                'expires_at' => $now->add(new DateInterval("PT{$expiryMinutes}M")),
            ]);

            // The recipient is an address, not a row: a link can be the first
            // thing a seller or a customer ever receives.
            Notification::route(MagicLinkIssued::channel(), $address)
                ->notify(new MagicLinkIssued(route('auth.magic.verify', $token)));

            $story->did('issued a sign-in link', [
                'magic_link_id' => $link->id,
                'actor_type' => $actorType->value,
                'expiry_minutes' => $expiryMinutes,
            ]);
        });
    }
}
