<?php

declare(strict_types=1);

namespace App\Domain\Shop;

use App\Domain\Auth\EmailAddress;
use App\Domain\Orders\Purchaser;
use DateTimeImmutable;

final class CheckoutPurchaser
{
    /**
     * A verified customer buys under the address on their account, so a
     * submitted field cannot move an order onto someone else's identity.
     */
    public static function forCustomer(
        int $customerId,
        ?string $accountEmail,
        ?DateTimeImmutable $emailVerifiedAt,
        string $submittedEmail,
    ): Purchaser {
        return $emailVerifiedAt === null
            ? new Purchaser($customerId, EmailAddress::normalize($submittedEmail), null)
            : new Purchaser($customerId, $accountEmail, $emailVerifiedAt);
    }
}
