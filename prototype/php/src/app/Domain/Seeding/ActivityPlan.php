<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

use DateTimeImmutable;

/**
 * A deterministic day-by-day script for a store that has been open for a
 * season: new customers signing up at a ramping pace, anonymous visitors
 * browsing and some of them verifying partway through, and sellers
 * creating and publishing new listings — everything
 * `App\Console\Commands\SeedActivity` needs to drive the real actions with
 * backdated moments. Nothing here touches the clock, the database, or PHP's
 * own random functions ({@see \Tests\Arch}, "the domain core stays pure"):
 * {@see generate()} takes the day it should end on as an argument, and
 * every choice a session's script makes is drawn from {@see Lcg}, seeded
 * once at the top. The same `$seed`, `$startDay`, and `$dayCount` always
 * produce the same plan.
 */
final readonly class ActivityPlan
{
    /** Roughly one seller publishing a new listing every dozen days. */
    private const int LISTING_CREATION_INTERVAL_DAYS = 12;

    /** The day-of-week offset a new listing lands on within its interval. */
    private const int LISTING_CREATION_DAY_OFFSET = 5;

    private function __construct(
        public int $seed,
        public DateTimeImmutable $startDay,
        public int $dayCount,
        /** @var list<Session> */
        public array $sessions,
        /** @var list<NewListingStep> */
        public array $listingCreations,
    ) {}

    /**
     * `$listingPoolSize` and `$sellerPoolSize` are how many listings and
     * sellers the plan has to address by index — `SeedActivity` resolves
     * those counts from the real, already-seeded rows before calling this,
     * so the domain core never has to know a listing or a seller beyond
     * its position in a list.
     */
    public static function generate(
        int $seed,
        DateTimeImmutable $startDay,
        int $dayCount,
        int $listingPoolSize,
        int $sellerPoolSize,
    ): self {
        $lcg = Lcg::seeded($seed);
        $rosterSize = count(HogwartsRoster::people());
        $templateCount = count(ListingTemplates::all());

        $sessions = [];
        $listingCreations = [];
        $nextPersonIndex = 0;
        $sessionCounter = 0;
        /** @var list<int> */
        $signedUpPersonIndexes = [];

        for ($dayIndex = 0; $dayIndex < $dayCount; $dayIndex++) {
            $day = $startDay->modify("+{$dayIndex} days");
            $weekendFactor = self::isWeekend($day) ? 7 : 10;

            // A returning visit only ever names someone who signed up on a
            // strictly earlier day — never today's own signups — so the
            // person it verifies as always already has a customer row by
            // the moment `SeedActivity` reaches this session, whatever the
            // two sessions' times of day land on within their own days.
            $eligibleToReturn = $signedUpPersonIndexes;

            array_push($sessions, ...self::signupSessions(
                $lcg, $dayIndex, $day, $dayCount, $weekendFactor, $rosterSize, $listingPoolSize,
                $nextPersonIndex, $sessionCounter, $signedUpPersonIndexes,
            ));

            array_push($sessions, ...self::visitSessions(
                $lcg, $dayIndex, $day, $dayCount, $weekendFactor, $listingPoolSize,
                $sessionCounter, $eligibleToReturn,
            ));

            if ($sellerPoolSize > 0 && self::createsListingOn($dayIndex)) {
                $listingCreations[] = self::buildListingCreation($lcg, $dayIndex, $day, $sellerPoolSize, $templateCount);
            }
        }

        return new self($seed, $startDay, $dayCount, $sessions, $listingCreations);
    }

    /**
     * The day's fresh signups: each one consumes the next unused roster
     * entry and shops under their own name from the moment they arrive.
     * Stops early once the roster runs out, so a long window never asks
     * for more people than {@see HogwartsRoster} can name.
     *
     * @param  list<int>  $signedUpPersonIndexes
     * @return list<Session>
     */
    private static function signupSessions(
        Lcg $lcg,
        int $dayIndex,
        DateTimeImmutable $day,
        int $dayCount,
        int $weekendFactor,
        int $rosterSize,
        int $listingPoolSize,
        int &$nextPersonIndex,
        int &$sessionCounter,
        array &$signedUpPersonIndexes,
    ): array {
        $count = intdiv(self::signupCountForDay($dayIndex, $dayCount) * $weekendFactor, 10);
        $sessions = [];

        for ($i = 0; $i < $count && $nextPersonIndex < $rosterSize; $i++) {
            $personIndex = $nextPersonIndex++;
            $signedUpPersonIndexes[] = $personIndex;
            $sessions[] = self::buildSession($lcg, $dayIndex, $day, $sessionCounter++, SessionKind::NewSignup, $personIndex, $listingPoolSize);
        }

        return $sessions;
    }

    /**
     * The day's anonymous traffic. One in six visits, once somebody has
     * signed up, returns and verifies as an already-used person instead of
     * staying anonymous — {@see SessionKind::ReturningVerify}, the session
     * kind whose steps `SeedActivity` folds into that person's existing
     * history through `MergeAnonymousCustomer`.
     *
     * @param  list<int>  $signedUpPersonIndexes
     * @return list<Session>
     */
    private static function visitSessions(
        Lcg $lcg,
        int $dayIndex,
        DateTimeImmutable $day,
        int $dayCount,
        int $weekendFactor,
        int $listingPoolSize,
        int &$sessionCounter,
        array $signedUpPersonIndexes,
    ): array {
        $count = intdiv(self::visitCountForDay($dayIndex, $dayCount) * $weekendFactor, 10);
        $sessions = [];

        for ($i = 0; $i < $count; $i++) {
            $returns = $signedUpPersonIndexes !== [] && $lcg->nextInt(6) === 0;
            $kind = $returns ? SessionKind::ReturningVerify : SessionKind::AnonymousBrowse;
            $personIndex = $returns ? $signedUpPersonIndexes[$lcg->nextInt(count($signedUpPersonIndexes))] : null;

            $sessions[] = self::buildSession($lcg, $dayIndex, $day, $sessionCounter++, $kind, $personIndex, $listingPoolSize);
        }

        return $sessions;
    }

    /**
     * A few signups a day in the first third of the window, several more
     * in the second, and a surge — capped so a long window never asks for
     * more people than a roster can name — in the last.
     */
    private static function signupCountForDay(int $dayIndex, int $dayCount): int
    {
        $third = max(1, intdiv($dayCount, 3));

        return match (true) {
            $dayIndex < $third => $dayIndex % 3 === 0 ? 1 : 0,
            $dayIndex < 2 * $third => 1,
            default => min(4, 2 + intdiv($dayIndex - 2 * $third, 8)),
        };
    }

    /**
     * Anonymous traffic ramps the same way signups do, at a higher volume:
     * most visits never sign up at all.
     */
    private static function visitCountForDay(int $dayIndex, int $dayCount): int
    {
        $third = max(1, intdiv($dayCount, 3));

        return match (true) {
            $dayIndex < $third => 3,
            $dayIndex < 2 * $third => 6,
            default => 10 + intdiv($dayIndex - 2 * $third, 10),
        };
    }

    private static function createsListingOn(int $dayIndex): bool
    {
        return $dayIndex % self::LISTING_CREATION_INTERVAL_DAYS === self::LISTING_CREATION_DAY_OFFSET;
    }

    private static function isWeekend(DateTimeImmutable $day): bool
    {
        return in_array((int) $day->format('N'), [6, 7], true);
    }

    private static function buildSession(
        Lcg $lcg,
        int $dayIndex,
        DateTimeImmutable $day,
        int $sessionCounter,
        SessionKind $kind,
        ?int $personIndex,
        int $listingPoolSize,
    ): Session {
        $at = self::eveningWeightedMoment($lcg, $day);
        $sessionId = sprintf('ses%05d', $sessionCounter);
        $ip = '203.0.113.'.($lcg->nextInt(40) + 1);
        $landingPath = $lcg->nextInt(10) < 8 ? '/' : '/art';
        $channel = self::pickChannel($lcg);

        return new Session(
            $dayIndex,
            $at,
            $sessionId,
            $ip,
            $landingPath,
            $kind,
            $personIndex,
            $channel,
            self::buildScript($lcg, $at, $kind, $listingPoolSize),
        );
    }

    /**
     * A visitor's script, in order: the listings they look at, whether they
     * favorite (and later unfavorite) one, whether they cart-add one and
     * then either abandon it or check out, and — a signed-in visitor only —
     * whether they ask a question. `$cursor` advances a few minutes with
     * every step, threaded through each stage by reference so the whole
     * script reads as one growing timeline.
     *
     * @return list<VisitStep>
     */
    private static function buildScript(Lcg $lcg, DateTimeImmutable $sessionStart, SessionKind $kind, int $listingPoolSize): array
    {
        $cursor = $sessionStart;
        $canTransact = $kind !== SessionKind::AnonymousBrowse;

        $viewSteps = self::viewSteps($lcg, $cursor, $listingPoolSize);
        $viewedSlots = array_map(fn (VisitStep $step): int => $step->listingSlot ?? 0, $viewSteps);

        return [
            ...$viewSteps,
            ...self::favoriteSteps($lcg, $cursor, $viewedSlots),
            ...self::cartAndCheckoutSteps($lcg, $cursor, $viewedSlots, $canTransact),
            ...self::questionSteps($lcg, $cursor, $viewedSlots, $canTransact),
        ];
    }

    /**
     * @return list<VisitStep>
     */
    private static function viewSteps(Lcg $lcg, DateTimeImmutable &$cursor, int $listingPoolSize): array
    {
        $viewCount = $lcg->nextInt(3) + 1;
        $steps = [];

        for ($i = 0; $i < $viewCount; $i++) {
            $slot = $listingPoolSize > 0 ? $lcg->nextInt($listingPoolSize) : 0;
            $steps[] = new VisitStep(StepKind::ListingView, self::nextMoment($lcg, $cursor), $slot);
        }

        return $steps;
    }

    /**
     * 30% of scripts favorite one of the listings they viewed; 30% of
     * those go on to unfavorite it — a change of mind, not a mistake.
     *
     * @param  list<int>  $viewedSlots
     * @return list<VisitStep>
     */
    private static function favoriteSteps(Lcg $lcg, DateTimeImmutable &$cursor, array $viewedSlots): array
    {
        if ($lcg->nextInt(10) >= 3) {
            return [];
        }

        $favoriteSlot = $viewedSlots[$lcg->nextInt(count($viewedSlots))];
        $steps = [new VisitStep(StepKind::Favorite, self::nextMoment($lcg, $cursor), $favoriteSlot)];

        if ($lcg->nextInt(10) < 3) {
            $steps[] = new VisitStep(StepKind::Unfavorite, self::nextMoment($lcg, $cursor), $favoriteSlot);
        }

        return $steps;
    }

    /**
     * 40% of scripts add a viewed listing to the cart. A signed-in
     * visitor's cart then checks out 55% of the time — placed and, 80% of
     * the time, paid; cancelled the other 20% — and is simply abandoned
     * the rest. An anonymous visitor's cart is always abandoned: neither
     * checkout nor a placed order names a customer who does not exist yet.
     *
     * @param  list<int>  $viewedSlots
     * @return list<VisitStep>
     */
    private static function cartAndCheckoutSteps(Lcg $lcg, DateTimeImmutable &$cursor, array $viewedSlots, bool $canTransact): array
    {
        if ($lcg->nextInt(10) >= 4) {
            return [];
        }

        $cartSlot = $viewedSlots[$lcg->nextInt(count($viewedSlots))];
        $steps = [new VisitStep(StepKind::CartAdd, self::nextMoment($lcg, $cursor), $cartSlot)];

        if (! $canTransact || $lcg->nextInt(20) >= 11) {
            return $steps;
        }

        $steps[] = new VisitStep(StepKind::CheckoutOpen, self::nextMoment($lcg, $cursor), null);
        $steps[] = new VisitStep(StepKind::OrderPlace, self::nextMoment($lcg, $cursor), null);
        $steps[] = $lcg->nextInt(10) < 8
            ? new VisitStep(StepKind::OrderPay, self::nextMoment($lcg, $cursor), null)
            : new VisitStep(StepKind::OrderCancel, self::nextMoment($lcg, $cursor), null);

        return $steps;
    }

    /**
     * 8% of signed-in visitors' scripts ask a question — half the time
     * about a listing they viewed, half the time a general support ask.
     *
     * @param  list<int>  $viewedSlots
     * @return list<VisitStep>
     */
    private static function questionSteps(Lcg $lcg, DateTimeImmutable &$cursor, array $viewedSlots, bool $canTransact): array
    {
        if (! $canTransact || $lcg->nextInt(100) >= 8) {
            return [];
        }

        $asksAboutListing = $lcg->nextInt(2) === 0;

        return [new VisitStep(
            $asksAboutListing ? StepKind::ListingQuestion : StepKind::SupportQuestion,
            self::nextMoment($lcg, $cursor),
            $asksAboutListing ? $viewedSlots[$lcg->nextInt(count($viewedSlots))] : null,
        )];
    }

    /**
     * Advances `$cursor` one to six minutes and returns the new moment —
     * every step in a script's timeline moves it forward the same way.
     */
    private static function nextMoment(Lcg $lcg, DateTimeImmutable &$cursor): DateTimeImmutable
    {
        $cursor = $cursor->modify('+'.($lcg->nextInt(6) + 1).' minutes');

        return $cursor;
    }

    private static function buildListingCreation(Lcg $lcg, int $dayIndex, DateTimeImmutable $day, int $sellerPoolSize, int $templateCount): NewListingStep
    {
        $sellerSlot = $lcg->nextInt($sellerPoolSize);
        $templateIndex = $lcg->nextInt($templateCount);
        $createdAt = self::businessHourMoment($lcg, $day);
        $publishedAt = self::businessHourMoment($lcg, $createdAt->modify('+'.($lcg->nextInt(3) + 1).' days'));

        return new NewListingStep($dayIndex, $createdAt, $publishedAt, $sellerSlot, $templateIndex);
    }

    /**
     * A moment weighted toward the evening: mostly 18:00-23:59, sometimes
     * the afternoon or morning, rarely overnight — "weekday and evening
     * weighted" traffic reads as more hits after work than during it.
     */
    private static function eveningWeightedMoment(Lcg $lcg, DateTimeImmutable $day): DateTimeImmutable
    {
        [$startHour, $span] = [[0, 8], [8, 4], [12, 6], [18, 6]][$lcg->weightedIndex([5, 15, 25, 55])];
        $hour = $startHour + $lcg->nextInt($span);

        return $day->setTime($hour, $lcg->nextInt(60), $lcg->nextInt(60));
    }

    private static function businessHourMoment(Lcg $lcg, DateTimeImmutable $day): DateTimeImmutable
    {
        return $day->setTime(9 + $lcg->nextInt(9), $lcg->nextInt(60), $lcg->nextInt(60));
    }

    /**
     * direct, a launch or newsletter campaign, a search engine, a social
     * network, or a referring site — the same taxonomy
     * {@see \App\Domain\Analytics\Channel::derive()} reads.
     */
    private static function pickChannel(Lcg $lcg): ChannelPick
    {
        $options = [
            fn (): ChannelPick => new ChannelPick(null, null, null, null),
            fn (): ChannelPick => new ChannelPick('newsletter', 'email', 'sept-launch', null),
            fn (): ChannelPick => new ChannelPick('newsletter', 'email', 'owl-post', null),
            fn (): ChannelPick => new ChannelPick(null, null, null, 'www.google.com'),
            fn (): ChannelPick => new ChannelPick(null, null, null, 'www.bing.com'),
            fn (): ChannelPick => new ChannelPick(null, null, null, 'www.instagram.com'),
            fn (): ChannelPick => new ChannelPick(null, null, null, 'www.pinterest.com'),
            fn (): ChannelPick => new ChannelPick(null, null, null, 'daily-prophet.example'),
        ];
        $weights = [30, 15, 10, 20, 8, 10, 5, 7];

        return $options[$lcg->weightedIndex($weights)]();
    }
}
