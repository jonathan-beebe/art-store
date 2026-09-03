<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

use DateTimeImmutable;

/**
 * A deterministic day-by-day script for a store that has been open for a
 * season: new customers signing up at a ramping pace, anonymous visitors
 * browsing and some of them verifying partway through, sellers creating
 * and publishing new listings, and — once the window is long enough to
 * hold a third month — a scraper and a prober scripted among the ordinary
 * traffic ({@see badActorSessions()}) — everything
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
    /** A window shorter than this never reaches a scripted third month, so
     * {@see badActorSessions()} sits out a short test window entirely. */
    private const int BAD_ACTOR_MIN_DAYS = 60;

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

            if ($sellerPoolSize > 0) {
                for ($i = self::listingCreationCountForDay($dayIndex, $dayCount); $i > 0; $i--) {
                    $listingCreations[] = self::buildListingCreation($lcg, $dayIndex, $day, $sellerPoolSize, $templateCount);
                }
            }
        }

        if ($dayCount >= self::BAD_ACTOR_MIN_DAYS) {
            array_push($sessions, ...self::badActorSessions($lcg, $startDay, $dayCount, $listingPoolSize, $sessionCounter));
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
     * A handful of signups across the first third of the window, roughly
     * one a day in the second, and a rising surge in the last — capped so
     * a long window never asks for more people than a roster can name.
     */
    private static function signupCountForDay(int $dayIndex, int $dayCount): int
    {
        $third = max(1, intdiv($dayCount, 3));

        return match (true) {
            $dayIndex < $third => $dayIndex % 3 === 0 ? 1 : 0,
            $dayIndex < 2 * $third => ($dayIndex - $third) % 4 === 0 ? 2 : 1,
            default => min(4, 1 + intdiv($dayIndex - 2 * $third, 6)),
        };
    }

    /**
     * Anonymous traffic ramps the same way signups do, at several times the
     * volume: most visits never sign up at all. The third month's rise
     * outpaces the first two by far — the surge {@see \App\Domain\Analytics\BarStrip}
     * draws as a visibly rising strip of daily bars.
     */
    private static function visitCountForDay(int $dayIndex, int $dayCount): int
    {
        $third = max(1, intdiv($dayCount, 3));

        return match (true) {
            $dayIndex < $third => 2,
            $dayIndex < 2 * $third => 3,
            default => 5 + intdiv($dayIndex - 2 * $third, 3),
        };
    }

    /**
     * How many listings a seller creates on a given day: rare in the first
     * third, a little more common in the second, and rising steeply in the
     * third — the catalog itself grows through the same surge that brings
     * the third month's flood of visitors, which is what lets the scraper
     * {@see badActorSessions()} scripts find more than a hundred listings
     * to hit inside one hour by the time it runs.
     */
    private static function listingCreationCountForDay(int $dayIndex, int $dayCount): int
    {
        $third = max(1, intdiv($dayCount, 3));

        return match (true) {
            $dayIndex < $third => $dayIndex % 9 === 0 ? 1 : 0,
            $dayIndex < 2 * $third => $dayIndex % 4 === 0 ? 1 : 0,
            default => min(6, 1 + intdiv($dayIndex - 2 * $third, 5)),
        };
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
        $viewCount = $lcg->nextInt(9) + 2;
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

    /**
     * The two bad actors: a scraper, one evening deep in the third month,
     * and a prober, scanning across several nights. Both are anonymous
     * ({@see SessionKind::Scraper}, {@see SessionKind::Prober} each name
     * nobody) and both stay outside the day-by-day ramp above — they are
     * scripted once, not drawn from a per-day count.
     *
     * @return list<Session>
     */
    private static function badActorSessions(Lcg $lcg, DateTimeImmutable $startDay, int $dayCount, int $listingPoolSize, int $sessionCounter): array
    {
        return [
            self::scraperSession($lcg, $startDay, $dayCount, $sessionCounter),
            self::proberSession($lcg, $startDay, $dayCount, $listingPoolSize, $sessionCounter + 1),
        ];
    }

    /**
     * One evening, five days from the end of the window: a single anonymous
     * visitor requesting a listing page every eight to ten seconds for
     * most of an hour, rotating between two addresses in a hosting range.
     * Every request is a plain {@see StepKind::ListingView} carrying the
     * dedupe key a real page load would — `SeedActivity` resolves its
     * `listingSlot` against the live catalog rather than `$listingPoolSize`,
     * since only the catalog a growing store has amassed by the third
     * month, not the pool this plan started from, holds enough listings to
     * carry the burst past {@see \App\Domain\Analytics\ActorVelocity}'s
     * threshold. No favorite, cart, or checkout step ever appears here.
     */
    private static function scraperSession(Lcg $lcg, DateTimeImmutable $startDay, int $dayCount, int $sessionCounter): Session
    {
        $dayIndex = max(0, $dayCount - 5);
        $day = $startDay->modify("+{$dayIndex} days");
        // The burst starts on the hour, so all but its last few seconds
        // land in one UTC-hour dedupe window rather than splitting across
        // two and halving what either one collects.
        $start = $day->setTime(19 + $lcg->nextInt(4), 0, $lcg->nextInt(3));
        $attemptCount = 340 + $lcg->nextInt(30);
        $octetA = 1 + $lcg->nextInt(254);
        $ipA = '185.220.101.'.$octetA;
        // Offset by half the range so the second address is always distinct
        // from the first, wrapping back into [1, 254].
        $ipB = '185.220.101.'.(1 + ($octetA + 126) % 254);

        $steps = [];
        $cursor = $start;
        for ($i = 0; $i < $attemptCount; $i++) {
            $ip = $lcg->nextInt(10) < 7 ? $ipA : $ipB;
            $steps[] = new VisitStep(StepKind::ListingView, $cursor, $i, $ip);
            $cursor = $cursor->modify('+'.(8 + $lcg->nextInt(3)).' seconds');
        }

        return new Session(
            $dayIndex,
            $start,
            sprintf('ses%05d', $sessionCounter),
            $ipA,
            '/art',
            SessionKind::Scraper,
            null,
            new ChannelPick(null, null, null, null),
            $steps,
        );
    }

    /**
     * One anonymous visitor scanning for credential and admin paths across
     * five nights, spaced roughly a week apart starting mid-window. A
     * couple of ordinary {@see StepKind::ListingView} steps open the
     * session, so it carries one real analytics event and an ip — without
     * one, it would name no actor at all. Every following step is a
     * {@see StepKind::ProbeRequest} against one of {@see ProbePaths}, one
     * to two seconds apart, which `SeedActivity` turns into log lines only:
     * a 404 or 302 never reaches the analytics store.
     */
    private static function proberSession(Lcg $lcg, DateTimeImmutable $startDay, int $dayCount, int $listingPoolSize, int $sessionCounter): Session
    {
        $firstNightDay = max(0, intdiv($dayCount, 2) - 3);
        $ip = '45.155.205.233';
        $firstNight = $startDay->modify("+{$firstNightDay} days")->setTime(21 + $lcg->nextInt(3), $lcg->nextInt(60), 0);

        $introSlot = $listingPoolSize > 0 ? $lcg->nextInt($listingPoolSize) : 0;
        $steps = [
            new VisitStep(StepKind::ListingView, $firstNight, $introSlot),
            new VisitStep(StepKind::ListingView, $firstNight->modify('+90 seconds'), $introSlot),
        ];

        $nightCount = 5;
        for ($night = 0; $night < $nightCount; $night++) {
            $nightDayIndex = min($dayCount - 1, $firstNightDay + $night * 6);
            $nightStart = $startDay->modify("+{$nightDayIndex} days")->setTime(22 + $lcg->nextInt(2), $lcg->nextInt(60), 0);
            array_push($steps, ...self::proberBurst($lcg, $nightStart, $ip));
        }

        return new Session(
            $firstNightDay,
            $firstNight,
            sprintf('ses%05d', $sessionCounter),
            $ip,
            '/',
            SessionKind::Prober,
            null,
            new ChannelPick(null, null, null, null),
            $steps,
        );
    }

    /**
     * One night's scan: {@see ProbePaths}, cycled as many times as it takes
     * to fill the burst, one to two seconds apart.
     *
     * @return list<VisitStep>
     */
    private static function proberBurst(Lcg $lcg, DateTimeImmutable $start, string $ip): array
    {
        $paths = ProbePaths::paths();
        $requestCount = 50 + $lcg->nextInt(20);
        $cursor = $start;
        $steps = [];

        for ($i = 0; $i < $requestCount; $i++) {
            $steps[] = new VisitStep(StepKind::ProbeRequest, $cursor, null, $ip, $paths[$i % count($paths)]);
            $cursor = $cursor->modify('+'.(1 + $lcg->nextInt(2)).' seconds');
        }

        return $steps;
    }
}
