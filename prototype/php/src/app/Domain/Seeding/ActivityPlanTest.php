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

it('never names a person outside the roster', function () use ($fullPlan): void {
    $plan = $fullPlan();
    $rosterSize = count(HogwartsRoster::people());

    foreach ($plan->sessions as $session) {
        if ($session->personIndex !== null) {
            expect($session->personIndex)->toBeGreaterThanOrEqual(0)->toBeLessThan($rosterSize);
        }
    }
});

it('never names a person for a purely anonymous session', function () use ($fullPlan): void {
    $plan = $fullPlan();

    foreach ($plan->sessions as $session) {
        if ($session->kind === SessionKind::AnonymousBrowse) {
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

it('addresses every listing view within the given listing pool', function () use ($fullPlan): void {
    $plan = $fullPlan();

    foreach ($plan->sessions as $session) {
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
