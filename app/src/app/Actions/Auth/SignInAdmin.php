<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Domain\Auth\EmailNormalizer;
use App\Models\Admin;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

final readonly class SignInAdmin
{
    /**
     * Admins are seeded, never signed up. An address with no admin row has no
     * account to sign into, so a link that outlives the row it was issued for
     * answers 404 rather than creating one.
     *
     * @throws ModelNotFoundException<Admin>
     */
    public function __invoke(string $email, DateTimeImmutable $now): Admin
    {
        $admin = Admin::query()->where('email', EmailNormalizer::normalize($email))->firstOrFail();

        if ($admin->email_verified_at === null) {
            $admin->forceFill(['email_verified_at' => $now])->save();
        }

        Auth::guard('admin')->login($admin);

        return $admin;
    }
}
