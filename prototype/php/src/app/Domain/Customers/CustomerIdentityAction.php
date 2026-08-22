<?php

namespace App\Domain\Customers;

enum CustomerIdentityAction
{
    case CreateVerified;
    case SignInExisting;
    case ClaimAnonymous;
    case MergeAnonymousInto;
}
