<?php

namespace App\Actions\Auth;

use App\Domain\Auth\EmailAddress;
use App\Models\Seller;
use Illuminate\Support\Facades\Auth;

final readonly class SignInSeller
{
    /**
     * A verified link is the whole of the seller sign-up flow: the first one
     * for an address creates the account.
     */
    public function __invoke(string $email): Seller
    {
        $seller = Seller::firstOrNew(['email' => EmailAddress::normalize($email)]);
        $seller->email_verified_at ??= now();
        $seller->save();

        Auth::guard('seller')->login($seller);

        return $seller;
    }
}
