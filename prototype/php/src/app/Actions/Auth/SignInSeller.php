<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Domain\Auth\EmailNormalizer;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Support\Facades\Auth;

final readonly class SignInSeller
{
    /**
     * A verified link is the whole of the seller sign-up flow: the first one
     * for an address creates the account.
     */
    public function __invoke(string $email, DateTimeImmutable $now): Seller
    {
        $seller = Seller::firstOrNew(['email' => EmailNormalizer::normalize($email)]);

        if ($seller->email_verified_at === null) {
            $seller->forceFill(['email_verified_at' => $now]);
        }

        $seller->save();

        Auth::guard('seller')->login($seller);

        return $seller;
    }
}
