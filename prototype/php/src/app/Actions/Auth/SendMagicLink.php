<?php

namespace App\Actions\Auth;

use App\Domain\Auth\ActorType;
use App\Domain\Auth\EmailAddress;
use App\Domain\Auth\MagicLinkToken;
use App\Models\MagicLink;
use App\Support\MagicLinkDelivery\MagicLinkDelivery;

final readonly class SendMagicLink
{
    private const TOKEN_BYTES = 40;

    public function __construct(private MagicLinkDelivery $delivery) {}

    public function __invoke(string $email, ActorType $actorType, ?string $redirectTo = null): void
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $address = EmailAddress::normalize($email);

        MagicLink::create([
            'token_hash' => MagicLinkToken::hash($token),
            'email' => $address,
            'actor_type' => $actorType,
            'redirect_to' => $redirectTo,
            'expires_at' => now()->addMinutes(config('magic_links.expiry_minutes')),
        ]);

        $this->delivery->deliver($address, route('auth.magic.verify', $token));
    }
}
