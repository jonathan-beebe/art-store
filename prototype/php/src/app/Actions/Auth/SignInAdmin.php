<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Domain\Auth\EmailNormalizer;
use App\Models\Admin;
use DateTimeImmutable;
use Illuminate\Support\Facades\Auth;

final readonly class SignInAdmin
{
    /**
     * Admins are seeded, never signed up: a verified link only ever reaches
     * this action once `SendAdminMagicLinkRequest` has already refused to
     * issue one for an address with no admin row.
     */
    public function __invoke(string $email, DateTimeImmutable $now): Admin
    {
        $admin = Admin::firstOrNew(['email' => EmailNormalizer::normalize($email)]);

        if ($admin->email_verified_at === null) {
            $admin->forceFill(['email_verified_at' => $now]);
        }

        $admin->save();

        Auth::guard('admin')->login($admin);

        return $admin;
    }
}
