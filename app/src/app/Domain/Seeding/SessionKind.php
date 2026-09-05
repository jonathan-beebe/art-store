<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

/**
 * What a seeded session is, ahead of any step it scripts: a fresh signup
 * shopping under their own name from the moment they arrive, an anonymous
 * visitor who never names themselves, an anonymous visitor who verifies
 * partway through and continues the session as whichever person that
 * verification resolves to — a fresh identity the first time a person's
 * email is used, a fold into that person's existing history every time
 * after — or one of the two bad actors {@see \App\Console\Commands\SeedActivity}
 * drives outside the ordinary script: a scraper hammering listing pages
 * fast enough to trip {@see \App\Domain\Analytics\ActorVelocity}, or a
 * prober probing for credential and admin paths that answer 404 or 302.
 */
enum SessionKind
{
    case NewSignup;
    case AnonymousBrowse;
    case ReturningVerify;
    case Scraper;
    case Prober;
}
