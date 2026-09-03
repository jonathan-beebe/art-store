<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

use DateTimeImmutable;

$fullPlan = static fn (int $seed = 2026): ActivityPlan => ActivityPlan::generate(
    $seed,
    new DateTimeImmutable('2026-06-03'),
    92,
    listingPoolSize: 24,
    sellerPoolSize: 6,
);

it('produces the same plan from the same seed, start day, and day count', function () use ($fullPlan): void {
    expect($fullPlan())->toEqual($fullPlan());
});

it('produces a different plan from a different seed', function () use ($fullPlan): void {
    expect($fullPlan(2026))->not->toEqual($fullPlan(2027));
});

it('ramps: the third month\'s new signups exceed the first month\'s several times over', function () use ($fullPlan): void {
    $plan = $fullPlan();
    $third = intdiv($plan->dayCount, 3);

    $signupsInMonth = fn (int $lowDay, int $highDay): int => count(array_filter(
        $plan->sessions,
        fn (Session $session): bool => $session->kind === SessionKind::NewSignup && $session->dayIndex >= $lowDay && $session->dayIndex < $highDay,
    ));

    $monthOne = $signupsInMonth(0, $third);
    $monthThree = $signupsInMonth(2 * $third, $plan->dayCount);

    expect($monthOne)->toBeGreaterThan(0)
        ->and($monthThree)->toBeGreaterThan($monthOne * 3);
});

it('ramps verified signups from a handful in month one to a surge in month three, visitors carrying the rest', function () use ($fullPlan): void {
    $plan = $fullPlan();
    $third = intdiv($plan->dayCount, 3);

    $countInMonth = fn (SessionKind $kind, int $lowDay, int $highDay): int => count(array_filter(
        $plan->sessions,
        fn (Session $session): bool => $session->kind === $kind && $session->dayIndex >= $lowDay && $session->dayIndex < $highDay,
    ));

    $signupsInMonth = fn (int $lowDay, int $highDay): int => $countInMonth(SessionKind::NewSignup, $lowDay, $highDay);
    $visitsInMonth = fn (int $lowDay, int $highDay): int => $countInMonth(SessionKind::AnonymousBrowse, $lowDay, $highDay)
        + $countInMonth(SessionKind::ReturningVerify, $lowDay, $highDay);

    $signupMonths = [
        $signupsInMonth(0, $third),
        $signupsInMonth($third, 2 * $third),
        $signupsInMonth(2 * $third, $plan->dayCount),
    ];
    $visitMonths = [
        $visitsInMonth(0, $third),
        $visitsInMonth($third, 2 * $third),
        $visitsInMonth(2 * $third, $plan->dayCount),
    ];

    // The day-modulo cadence and the roster size bound these to a range
    // around 8/30/80, the three months' target shape.
    expect($signupMonths[0])->toBeGreaterThanOrEqual(5)->toBeLessThanOrEqual(12)
        ->and($signupMonths[1])->toBeGreaterThanOrEqual(20)->toBeLessThanOrEqual(35)
        ->and($signupMonths[2])->toBeGreaterThanOrEqual(65)->toBeLessThanOrEqual(90)
        ->and($signupMonths[0])->toBeLessThan($signupMonths[1])
        ->and($signupMonths[1])->toBeLessThan($signupMonths[2]);

    // Visitor traffic outgrows the signup curve by far in every month —
    // the surge is anonymous.
    foreach ($signupMonths as $index => $signupCount) {
        expect($visitMonths[$index])->toBeGreaterThan($signupCount * 2);
    }
    expect($visitMonths[0])->toBeLessThan($visitMonths[1])
        ->and($visitMonths[1])->toBeLessThan($visitMonths[2]);
});

it('never names a person outside the roster', function () use ($fullPlan): void {
    $plan = $fullPlan();
    $rosterSize = count(HogwartsRoster::people());

    foreach ($plan->sessions as $session) {
        if ($session->personIndex !== null) {
            expect($session->personIndex)->toBeGreaterThanOrEqual(0)->toBeLessThan($rosterSize);
        }
    }
});

it('never names a person for an anonymous session, ordinary or bad actor', function () use ($fullPlan): void {
    $plan = $fullPlan();
    $anonymousKinds = [SessionKind::AnonymousBrowse, SessionKind::Scraper, SessionKind::Prober];

    foreach ($plan->sessions as $session) {
        if (in_array($session->kind, $anonymousKinds, true)) {
            expect($session->personIndex)->toBeNull();
        } else {
            expect($session->personIndex)->not->toBeNull();
        }
    }
});

it('scripts at least one of every step kind across a full plan', function () use ($fullPlan): void {
    $plan = $fullPlan();

    $kinds = [];

    foreach ($plan->sessions as $session) {
        foreach ($session->steps as $step) {
            $kinds[$step->kind->name] = true;
        }
    }

    foreach (StepKind::cases() as $case) {
        expect($kinds)->toHaveKey($case->name);
    }
});

it('never scripts a transacting step for a purely anonymous session', function () use ($fullPlan): void {
    $plan = $fullPlan();
    $transacting = [StepKind::CheckoutOpen, StepKind::OrderPlace, StepKind::OrderPay, StepKind::OrderCancel, StepKind::ListingQuestion, StepKind::SupportQuestion];

    foreach ($plan->sessions as $session) {
        if ($session->kind !== SessionKind::AnonymousBrowse) {
            continue;
        }

        foreach ($session->steps as $step) {
            expect($step->kind)->not->toBeIn($transacting);
        }
    }
});

it('schedules at least one seller creating a new listing across the period', function () use ($fullPlan): void {
    expect($fullPlan()->listingCreations)->not->toBe([]);
});

it('addresses every listing creation within the given seller and template pools', function () use ($fullPlan): void {
    $plan = $fullPlan();

    foreach ($plan->listingCreations as $creation) {
        expect($creation->sellerSlot)->toBeGreaterThanOrEqual(0)->toBeLessThan(6)
            ->and($creation->templateIndex)->toBeGreaterThanOrEqual(0)->toBeLessThan(count(ListingTemplates::all()))
            ->and($creation->publishedAt)->toBeGreaterThan($creation->createdAt);
    }
});

it('addresses every ordinary listing view within the given listing pool', function () use ($fullPlan): void {
    $plan = $fullPlan();

    // The scraper is the one exception: `SeedActivity` resolves its steps
    // against the live catalog — see its own docblock — so its slots run
    // well past a small test pool.
    foreach ($plan->sessions as $session) {
        if ($session->kind === SessionKind::Scraper) {
            continue;
        }

        foreach ($session->steps as $step) {
            if ($step->listingSlot !== null) {
                expect($step->listingSlot)->toBeGreaterThanOrEqual(0)->toBeLessThan(24);
            }
        }
    }
});

it('creates no seller listing when the plan is given no sellers', function (): void {
    $plan = ActivityPlan::generate(2026, new DateTimeImmutable('2026-06-03'), 92, listingPoolSize: 24, sellerPoolSize: 0);

    expect($plan->listingCreations)->toBe([]);
});

it('never crashes when given an empty listing pool', function (): void {
    $plan = ActivityPlan::generate(2026, new DateTimeImmutable('2026-06-03'), 14, listingPoolSize: 0, sellerPoolSize: 6);

    expect($plan->sessions)->not->toBe([]);
});

it('produces no session for a zero-day window', function (): void {
    $plan = ActivityPlan::generate(2026, new DateTimeImmutable('2026-06-03'), 0, listingPoolSize: 24, sellerPoolSize: 6);

    expect($plan->sessions)->toBe([])
        ->and($plan->listingCreations)->toBe([]);
});

it('carries the seed, start day, and day count it was built from', function (): void {
    $startDay = new DateTimeImmutable('2026-06-03');
    $plan = ActivityPlan::generate(2026, $startDay, 7, listingPoolSize: 24, sellerPoolSize: 6);

    expect($plan->seed)->toBe(2026)
        ->and($plan->startDay)->toBe($startDay)
        ->and($plan->dayCount)->toBe(7);
});

it('never returns a person on the same day they signed up', function () use ($fullPlan): void {
    $plan = $fullPlan();

    $signupDayByPerson = [];

    foreach ($plan->sessions as $session) {
        if ($session->kind === SessionKind::NewSignup) {
            $signupDayByPerson[$session->personIndex] = $session->dayIndex;
        }
    }

    foreach ($plan->sessions as $session) {
        if ($session->kind === SessionKind::ReturningVerify) {
            $personIndex = $session->personIndex ?? -1;

            expect($signupDayByPerson)->toHaveKey($personIndex)
                ->and($session->dayIndex)->toBeGreaterThan($signupDayByPerson[$personIndex]);
        }
    }
});

it('keeps every session within the given day count', function () use ($fullPlan): void {
    $plan = $fullPlan();

    foreach ($plan->sessions as $session) {
        expect($session->dayIndex)->toBeGreaterThanOrEqual(0)->toBeLessThan($plan->dayCount);
    }
});

it('scripts no bad actor inside a window shorter than a third month', function (): void {
    $plan = ActivityPlan::generate(2026, new DateTimeImmutable('2026-06-03'), 59, listingPoolSize: 24, sellerPoolSize: 6);

    $kinds = array_map(fn (Session $session): SessionKind => $session->kind, $plan->sessions);

    expect($kinds)->not->toContain(SessionKind::Scraper)
        ->and($kinds)->not->toContain(SessionKind::Prober);
});

it('scripts exactly one scraper hammering listing views at high velocity', function () use ($fullPlan): void {
    $plan = $fullPlan();
    $scrapers = array_values(array_filter($plan->sessions, fn (Session $session): bool => $session->kind === SessionKind::Scraper));

    expect($scrapers)->toHaveCount(1);

    $scraper = $scrapers[0];
    $seconds = array_map(fn (VisitStep $step): int => (int) $step->at->format('U'), $scraper->steps);
    $gaps = [];
    for ($i = 1; $i < count($seconds); $i++) {
        $gaps[] = $seconds[$i] - $seconds[$i - 1];
    }

    expect($scraper->steps)->not->toBe([])
        ->and(count($scraper->steps))->toBeGreaterThan(300)
        ->and(array_unique(array_map(fn (VisitStep $step): string => $step->kind->name, $scraper->steps)))->toBe([StepKind::ListingView->name])
        ->and($gaps)->not->toBe([]);

    foreach ($gaps as $gap) {
        expect($gap)->toBeGreaterThanOrEqual(8)->toBeLessThanOrEqual(10);
    }
});

it("rotates the scraper's requests across two addresses in one hosting range", function () use ($fullPlan): void {
    $scraper = array_values(array_filter($fullPlan()->sessions, fn (Session $session): bool => $session->kind === SessionKind::Scraper))[0];

    $ips = array_unique(array_map(fn (VisitStep $step): string => $step->ip ?? '', $scraper->steps));

    expect($ips)->toHaveCount(2);

    foreach ($ips as $ip) {
        expect($ip)->toStartWith('185.220.101.');
    }
});

it('scripts exactly one prober scanning credential and admin paths', function () use ($fullPlan): void {
    $plan = $fullPlan();
    $probers = array_values(array_filter($plan->sessions, fn (Session $session): bool => $session->kind === SessionKind::Prober));

    expect($probers)->toHaveCount(1);

    $prober = $probers[0];
    $probeSteps = array_values(array_filter($prober->steps, fn (VisitStep $step): bool => $step->kind === StepKind::ProbeRequest));
    $viewSteps = array_values(array_filter($prober->steps, fn (VisitStep $step): bool => $step->kind === StepKind::ListingView));

    expect($probeSteps)->not->toBe([])
        ->and($viewSteps)->not->toBe([])
        ->and(count($probeSteps))->toBeGreaterThan(200);

    foreach ($probeSteps as $step) {
        expect(ProbePaths::paths())->toContain($step->path)
            ->and($step->ip)->toBe('45.155.205.233');
    }
});

it('scans across several distinct nights, not one sitting', function () use ($fullPlan): void {
    $prober = array_values(array_filter($fullPlan()->sessions, fn (Session $session): bool => $session->kind === SessionKind::Prober))[0];

    $probeSteps = array_values(array_filter($prober->steps, fn (VisitStep $step): bool => $step->kind === StepKind::ProbeRequest));
    $nights = array_unique(array_map(fn (VisitStep $step): string => $step->at->format('Y-m-d'), $probeSteps));

    expect(count($nights))->toBeGreaterThanOrEqual(5);
});

it('never scripts a transacting or favoriting step for either bad actor', function () use ($fullPlan): void {
    $plan = $fullPlan();
    $offLimits = [StepKind::Favorite, StepKind::Unfavorite, StepKind::CartAdd, StepKind::CheckoutOpen, StepKind::OrderPlace, StepKind::OrderPay, StepKind::OrderCancel];
    $badActors = array_filter($plan->sessions, fn (Session $session): bool => in_array($session->kind, [SessionKind::Scraper, SessionKind::Prober], true));

    foreach ($badActors as $session) {
        foreach ($session->steps as $step) {
            expect($step->kind)->not->toBeIn($offLimits);
        }
    }
});
