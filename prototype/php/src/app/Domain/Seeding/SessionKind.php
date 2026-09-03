<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

/**
 * What a seeded session is, ahead of any step it scripts: a fresh signup
 * shopping under their own name from the moment they arrive, an anonymous
 * visitor who never names themselves, or an anonymous visitor who verifies
 * partway through and continues the session as whichever person that
 * verification resolves to — a fresh identity the first time a person's
 * email is used, a fold into that person's existing history every time
 * after.
 */
enum SessionKind
{
    case NewSignup;
    case AnonymousBrowse;
    case ReturningVerify;
}
