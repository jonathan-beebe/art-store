<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

/**
 * One step of a seeded visitor's script — the vocabulary
 * `App\Domain\Seeding\ActivityPlan` draws a session's `VisitStep` list
 * from, and `App\Console\Commands\SeedActivity` reads back to decide which
 * real action a step drives. `ProbeRequest` is the one kind naming no
 * listing and no real action: a probe against a credential or admin path
 * that a real server would answer 404 or 302, so it never reaches the
 * analytics store — only the log store carries it.
 */
enum StepKind
{
    case ListingView;
    case Favorite;
    case Unfavorite;
    case CartAdd;
    case CheckoutOpen;
    case OrderPlace;
    case OrderPay;
    case OrderCancel;
    case ListingQuestion;
    case SupportQuestion;
    case ProbeRequest;
}
