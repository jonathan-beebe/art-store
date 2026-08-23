<?php

declare(strict_types=1);

namespace App\Domain\Customers;

enum CustomerIdentityAction
{
    case CreateVerified;
    case SignInExisting;
    case ClaimAnonymous;
    case MergeAnonymousInto;
}
