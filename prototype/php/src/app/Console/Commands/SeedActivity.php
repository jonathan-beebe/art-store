<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Cart\AddToCart;
use App\Actions\Customers\ClaimCustomerIdentity;
use App\Actions\Favorites\ToggleFavorite;
use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Listings\CreateListing;
use App\Actions\Messaging\OpenThread;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\PlaceOrder;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Analytics\AnalyticsVisit;
use App\Analytics\RequestFacts;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\PageViewSite;
use App\Domain\Auth\ActorType;
use App\Domain\Listings\ListingDraft;
use App\Domain\Listings\ListingStatus;
use App\Domain\Listings\ListingViewCollapse;
use App\Domain\Messaging\MessageBody;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\Messaging\ThreadTitle;
use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\Purchaser;
use App\Domain\Orders\ShippingAddress;
use App\Domain\Seeding\ActivityPlan;
use App\Domain\Seeding\HogwartsRoster;
use App\Domain\Seeding\ListingTemplates;
use App\Domain\Seeding\NewListingStep;
use App\Domain\Seeding\ProbePaths;
use App\Domain\Seeding\Session;
use App\Domain\Seeding\SessionKind;
use App\Domain\Seeding\StepKind;
use App\Domain\Seeding\VisitStep;
use App\Logging\LogLine;
use App\Logging\LogStore;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\Order;
use App\Models\Property;
use App\Models\Seller;
use App\Support\IdMint;
use App\Support\Story;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * A local-dev-only command: fills the app database, the analytics store,
 * and the log store (through the same `Story` lines every action already
 * writes) with a deterministic ninety-plus day ramp of store activity,
 * built from {@see ActivityPlan}. Refuses in production and refuses a
 * second run, so `make fresh` followed by this command is always safe to
 * repeat from a clean database.
 *
 * Every step is driven through the real action a shopper's or seller's own
 * request would call, backdated with the moment {@see ActivityPlan} chose
 * for it and wrapped in {@see Analytics::asRequest()} so the analytics
 * store agrees with the app database on when, and from where, each thing
 * happened. A drawn step can still collide with real store state — a
 * listing that sold out since an earlier visitor viewed it, for
 * instance — the same refusal a real shopper would hit; that step is
 * skipped rather than aborting the run.
 */
final class SeedActivity extends Command
{
    /** @var string */
    protected $signature = 'seed:activity {--days=92 : how many days of activity to generate, ending today} {--seed=2026 : the deterministic seed the activity plan draws from}';

    /** @var string */
    protected $description = 'Fill the app database and analytics store with a ramp of ninety-plus days of store activity — local development only';

    private const string APPROVED_CARD = '4242424242424242';

    /** @var array<string, string> a medium key to the exact Medium property label it does not title-case onto */
    private const array MEDIUM_LABEL_OVERRIDES = ['photography' => 'Photograph'];

    /** @var list<string> */
    private const array LISTING_QUESTIONS = [
        'Does this ship internationally?',
        'Is this still available?',
        'Could you show a photo of the back?',
        'What is the turnaround time if I order today?',
        'Do you offer this in a smaller size?',
        'Is this handmade or printed?',
    ];

    /** @var list<string> */
    private const array SUPPORT_QUESTIONS = [
        'I never received a confirmation email for a recent order — can you check on it?',
        'Can I change the shipping address on an order I just placed?',
        'How long does a refund normally take to show up?',
        'Is there a way to combine shipping on two separate orders?',
        'My tracking number has not updated in a few days — is that normal?',
    ];

    /** The order the current session's most recent OrderPlace step placed,
     * for the OrderPay or OrderCancel step right after it to act on. Reset
     * at the start of every session, since sessions run one at a time. */
    private ?Order $currentOrder = null;

    public function handle(Analytics $analytics, LogStore $logStore): int
    {
        if (app()->isProduction()) {
            $this->error('seed:activity refuses to run in production.');

            return self::FAILURE;
        }

        if (DB::table('seed_runs')->exists()) {
            $this->error('seed:activity has already run once — its marker is the seed_runs row. make fresh first to run it again.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $seed = (int) $this->option('seed');
        $now = now()->toDateTimeImmutable();
        $startDay = $now->modify('-'.($days - 1).' days')->setTime(0, 0, 0);

        /** @var list<Listing> $listings */
        $listings = Listing::query()->forSale()->orderBy('id')->get()->values()->all();
        /** @var list<Seller> $sellers */
        $sellers = Seller::query()->orderBy('id')->get()->values()->all();

        if ($listings === [] || $sellers === []) {
            $this->error("seed:activity needs make fresh's sellers and listings seeded first.");

            return self::FAILURE;
        }

        $customersBefore = Customer::query()->count();
        $ordersBefore = Order::query()->count();

        $plan = ActivityPlan::generate($seed, $startDay, $days, count($listings), count($sellers));
        $roster = HogwartsRoster::people();

        $this->info(sprintf(
            'Driving %d days of activity from %s to %s (seed %d)...',
            $days,
            $startDay->format('Y-m-d'),
            $now->format('Y-m-d'),
            $seed,
        ));

        foreach ($this->chronological($plan) as $item) {
            if ($item instanceof Session) {
                $this->runSession($item, $roster, $listings, $analytics, $logStore);
            } else {
                $this->runListingCreation($item, $sellers);
            }
        }

        $this->runFulfillments($now);
        $this->runPayouts($now, $days);

        $analytics->flush();
        $logStore->flush();

        DB::table('seed_runs')->insert([
            'id' => IdMint::of('sdr'),
            'seed' => $seed,
            'day_count' => $days,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->report($customersBefore, $ordersBefore);

        return self::SUCCESS;
    }

    /**
     * Sessions and listing creations, interleaved by the moment each
     * happened — a listing a seller creates on day forty is never in the
     * pool a session drawn before day forty could have viewed, since
     * `ActivityPlan` sized that pool from what existed before this command
     * ran; this ordering only keeps every log line and analytics row in
     * the sequence a reader expects.
     *
     * @return list<Session|NewListingStep>
     */
    private function chronological(ActivityPlan $plan): array
    {
        $items = [...$plan->sessions, ...$plan->listingCreations];

        usort($items, fn (Session|NewListingStep $a, Session|NewListingStep $b): int => $this->moment($a) <=> $this->moment($b));

        return $items;
    }

    private function moment(Session|NewListingStep $item): DateTimeImmutable
    {
        return $item instanceof Session ? $item->at : $item->createdAt;
    }

    /**
     * Every session kind but the two bad actors drives its steps through
     * the real actions {@see runOrdinarySession()} names. A scraper and a
     * prober are scripted outside that pipeline — see each method's own
     * docblock for why.
     *
     * @param  list<array{name: string, email: string}>  $roster
     * @param  list<Listing>  $listings
     */
    private function runSession(Session $session, array $roster, array $listings, Analytics $analytics, LogStore $logStore): void
    {
        match ($session->kind) {
            SessionKind::Scraper => $this->runScraperSession($session, $analytics, $logStore),
            SessionKind::Prober => $this->runProberSession($session, $analytics, $logStore),
            default => $this->runOrdinarySession($session, $roster, $listings, $analytics),
        };
    }

    /**
     * Resolves who the session is, records its first-touch visit, then
     * drives every step in order, each under its own minted request id and
     * `Analytics::asRequest()` scope so the ip, session, and request id an
     * action's own `recordEvent()` call fills in match the ones this
     * session's visit and log lines carry.
     *
     * @param  list<array{name: string, email: string}>  $roster
     * @param  list<Listing>  $listings
     */
    private function runOrdinarySession(Session $session, array $roster, array $listings, Analytics $analytics): void
    {
        $customer = $this->resolveCustomer($session, $roster);
        $this->currentOrder = null;

        Story::inSession($session->sessionId);
        Story::actorIs(ActorType::Customer, $customer->id);

        $analytics->recordVisit(AnalyticsVisit::of(
            $session->sessionId,
            $session->at,
            $session->landingPath,
            $session->channel->referrerHost,
            [
                'source' => $session->channel->utmSource,
                'medium' => $session->channel->utmMedium,
                'campaign' => $session->channel->utmCampaign,
            ],
            $customer->id,
        ));

        foreach ($session->steps as $step) {
            $requestId = IdMint::of('req');
            Story::follows($requestId);
            $facts = RequestFacts::of($session->ip, $session->sessionId, $requestId);

            try {
                $analytics->asRequest($facts, function () use ($step, $customer, $listings, $analytics): void {
                    $this->runStep($step, $customer, $listings, $analytics);
                });
            } catch (Throwable) {
                // A drawn step can collide with real store state — see this
                // class's own docblock. Skipped, not fatal.
            }
        }
    }

    /**
     * The scraper: every step is a plain listing-view request, resolved
     * against the live catalog — {@see ActivityPlan::scraperSession()}'s
     * own docblock says why this bypasses the ordinary pool — rather than
     * `$listings`, so a burst deep in the third month reaches a catalog
     * this command's own fixed pool never grew to. A live query, not a
     * cached one: every listing the plan's own `NewListingStep`s created
     * earlier in this same run already exists in the database by now,
     * `chronological()`'s ordering being what makes that true.
     */
    private function runScraperSession(Session $session, Analytics $analytics, LogStore $logStore): void
    {
        /** @var list<Listing> $liveListings */
        $liveListings = Listing::query()->forSale()->orderBy('id')->get()->values()->all();

        if ($liveListings === []) {
            return;
        }

        $customer = $this->createAnonymous($session->at);

        Story::inSession($session->sessionId);
        Story::actorIs(ActorType::Customer, $customer->id);

        $analytics->recordVisit(AnalyticsVisit::of(
            $session->sessionId,
            $session->at,
            $session->landingPath,
            $session->channel->referrerHost,
            ['source' => null, 'medium' => null, 'campaign' => null],
            $customer->id,
        ));

        foreach ($session->steps as $step) {
            $listing = $liveListings[($step->listingSlot ?? 0) % count($liveListings)];
            $ip = $step->ip ?? $session->ip;
            $requestId = IdMint::of('req');
            Story::follows($requestId);
            $facts = RequestFacts::of($ip, $session->sessionId, $requestId);

            try {
                $analytics->asRequest($facts, function () use ($listing, $customer, $step, $analytics): void {
                    $analytics->recordEvent(AnalyticsEvent::forListing(
                        AnalyticsEventName::ListingView,
                        $listing->id,
                        $customer->id,
                        $step->at,
                        ListingViewCollapse::dedupeKey($listing->id, $customer->id, $step->at),
                    ));
                });
                $this->logListingRequest($logStore, $listing, $requestId, $session->sessionId, $customer->id, $step->at, $analytics);
            } catch (Throwable) {
                // A dedupe collision or a listing that sold out mid-run —
                // skipped, the same tolerance every other session gets.
            }
        }
    }

    /**
     * The prober: a couple of ordinary listing views — the only reason
     * this actor carries a real analytics event and an ip an admin can
     * search for at all, since every following step answers 404 or 302 and
     * `App\Domain\Analytics\PageViewCountability` keeps a non-2xx response
     * out of the roll-up by design — then a `ProbeRequest` burst across
     * several nights, each one only ever an `http.request` log line: no
     * route this application registers answers any of these paths, so no
     * analytics event, and no domain story line, is possible for one.
     */
    private function runProberSession(Session $session, Analytics $analytics, LogStore $logStore): void
    {
        $customer = $this->createAnonymous($session->at);

        Story::inSession($session->sessionId);
        Story::actorIs(ActorType::Customer, $customer->id);

        $analytics->recordVisit(AnalyticsVisit::of(
            $session->sessionId,
            $session->at,
            $session->landingPath,
            $session->channel->referrerHost,
            ['source' => null, 'medium' => null, 'campaign' => null],
            $customer->id,
        ));

        /** @var list<Listing> $listings */
        $listings = Listing::query()->forSale()->orderBy('id')->get()->values()->all();

        foreach ($session->steps as $step) {
            $requestId = IdMint::of('req');
            Story::follows($requestId);
            $facts = RequestFacts::of($session->ip, $session->sessionId, $requestId);

            if ($step->kind === StepKind::ProbeRequest) {
                $this->logProbeRequest($logStore, $step, $requestId, $session->sessionId, $customer->id);

                continue;
            }

            if ($listings === []) {
                continue;
            }

            $listing = $listings[($step->listingSlot ?? 0) % count($listings)];

            try {
                $analytics->asRequest($facts, function () use ($listing, $customer, $step, $analytics): void {
                    $analytics->recordEvent(AnalyticsEvent::forListing(
                        AnalyticsEventName::ListingView,
                        $listing->id,
                        $customer->id,
                        $step->at,
                        ListingViewCollapse::dedupeKey($listing->id, $customer->id, $step->at),
                    ));
                });
                $this->logListingRequest($logStore, $listing, $requestId, $session->sessionId, $customer->id, $step->at, $analytics);
            } catch (Throwable) {
                // Same tolerance as every other session.
            }
        }
    }

    /**
     * The lines one real listing-page load writes: the page-view roll-up
     * and the `http.request` pair — {@see \App\Http\Middleware\LogRequestStory}'s
     * own shape, captured with `Tests\CapturedStory` against a running
     * request before this was written.
     */
    private function logListingRequest(LogStore $logStore, Listing $listing, string $requestId, string $sessionId, string $actorId, DateTimeImmutable $at, Analytics $analytics): void
    {
        $path = "/art/{$listing->slug}";
        $durationMs = $this->fakeDuration($requestId);

        $analytics->recordPageView(PageViewSite::Shop, $path, $at);
        $this->logHttpLine($logStore, 'GET', $path, 200, $at, $requestId, $sessionId, $actorId, $durationMs);
    }

    /**
     * A prober's one line: the `http.request` pair a real request against
     * one of {@see ProbePaths} would have written, 404 or 302, and nothing
     * else — no route answers any of these, so no domain story line ever
     * could.
     */
    private function logProbeRequest(LogStore $logStore, VisitStep $step, string $requestId, string $sessionId, string $actorId): void
    {
        $path = $step->path ?? '/';
        $status = ProbePaths::statusFor($path);

        $this->logHttpLine($logStore, 'GET', $path, $status, $step->at, $requestId, $sessionId, $actorId, $this->fakeDuration($requestId));
    }

    /**
     * The `http.request` will/did pair — {@see \App\Http\Middleware\LogRequestStory}'s
     * own shape: `will` carries `method`/`path` only, `did` carries
     * `status` and a `db` tally, and neither carries an `ip` — the real
     * line does not either, an actor's ip living only in the analytics
     * store's own `ip` column.
     */
    private function logHttpLine(LogStore $logStore, string $method, string $path, int $status, DateTimeImmutable $at, string $requestId, string $sessionId, string $actorId, int $durationMs): void
    {
        $didAt = $at->modify("+{$durationMs} milliseconds");

        $this->appendLine($logStore, $at, $requestId, $sessionId, $actorId, 'http.request', 'will', "{$method} {$path}", [
            'method' => $method,
            'path' => $path,
        ], null);

        $this->appendLine($logStore, $didAt, $requestId, $sessionId, $actorId, 'http.request', 'did', "{$method} {$path} {$status}", [
            'status' => $status,
            'db' => ['queries' => 2 + ($durationMs % 6), 'total_ms' => round($durationMs * 0.35, 1)],
        ], $durationMs);
    }

    /**
     * One deployed-shaped JSON log line, appended straight to the log
     * store — {@see LogLine::parse()} turns it into the same
     * row a stdout line would become. `$data` empty is a line with no
     * `data` key at all, the same as `App\Logging\StoryFormatter` omits it.
     *
     * @param  array<string, mixed>  $data
     */
    private function appendLine(
        LogStore $logStore,
        DateTimeImmutable $at,
        string $requestId,
        string $sessionId,
        string $actorId,
        string $event,
        string $phase,
        string $msg,
        array $data,
        ?int $durationMs,
    ): void {
        $payload = array_filter([
            'ts' => $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'level' => 'info',
            'event' => $event,
            'phase' => $phase,
            'msg' => $msg,
            'request_id' => $requestId,
            'session_id' => $sessionId,
            'actor_type' => ActorType::Customer->value,
            'actor_id' => $actorId,
            'data' => $data === [] ? null : $data,
            'duration_ms' => $durationMs,
        ], fn (mixed $value): bool => $value !== null);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json !== false) {
            $logStore->append(LogLine::parse($json));
        }
    }

    /**
     * A small, deterministic stand-in for wall time: the same request id
     * always fakes the same duration, so a test asserting on one seeded
     * line's shape never flakes on a value nobody drove from `Lcg`.
     */
    private function fakeDuration(string $requestId): int
    {
        return 12 + (crc32($requestId) % 160);
    }

    /**
     * @param  list<array{name: string, email: string}>  $roster
     */
    private function resolveCustomer(Session $session, array $roster): Customer
    {
        return match ($session->kind) {
            SessionKind::AnonymousBrowse => $this->createAnonymous($session->at),
            SessionKind::NewSignup => $this->createSignup($session->at, $roster[$this->personIndex($session)]),
            SessionKind::ReturningVerify => $this->verifyReturning($session->at, $roster[$this->personIndex($session)]),
            SessionKind::Scraper, SessionKind::Prober => throw new LogicException('A bad actor session never resolves a customer this way — see runScraperSession()/runProberSession().'),
        };
    }

    private function personIndex(Session $session): int
    {
        return $session->personIndex ?? throw new LogicException('A signup or returning session always names a person.');
    }

    private function createAnonymous(DateTimeImmutable $at): Customer
    {
        $customer = Customer::create([]);
        $this->backdate($customer, $at);

        return $customer;
    }

    /**
     * @param  array{name: string, email: string}  $person
     */
    private function createSignup(DateTimeImmutable $at, array $person): Customer
    {
        $customer = Customer::create([
            'email' => $person['email'],
            'name' => $person['name'],
            'email_verified_at' => $at,
        ]);
        $this->backdate($customer, $at);

        return $customer;
    }

    /**
     * A fresh anonymous row arrives this session, then claims the roster
     * email it returns under — {@see ClaimCustomerIdentity} folds it into
     * that person's existing signup through `MergeAnonymousCustomer`,
     * since the email already names a verified customer by the time any
     * `ReturningVerify` session is drawn.
     *
     * @param  array{name: string, email: string}  $person
     */
    private function verifyReturning(DateTimeImmutable $at, array $person): Customer
    {
        $anonymous = $this->createAnonymous($at);

        return app(ClaimCustomerIdentity::class)($person['email'], $anonymous, $at);
    }

    private function backdate(Customer $customer, DateTimeImmutable $at): void
    {
        $customer->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }

    /**
     * @param  list<Listing>  $listings
     */
    private function runStep(VisitStep $step, Customer $customer, array $listings, Analytics $analytics): void
    {
        match ($step->kind) {
            StepKind::ListingView => $this->recordListingView($step, $customer, $listings, $analytics),
            StepKind::Favorite, StepKind::Unfavorite => $this->toggleFavorite($step, $customer, $listings),
            StepKind::CartAdd => $this->addToCart($step, $customer, $listings),
            StepKind::CheckoutOpen => $this->openCheckout($step, $customer, $analytics),
            StepKind::OrderPlace => $this->placeOrder($step, $customer),
            StepKind::OrderPay => $this->payOrder($step),
            StepKind::OrderCancel => $this->cancelOrder($step),
            StepKind::ListingQuestion => $this->askListingQuestion($step, $customer, $listings),
            StepKind::SupportQuestion => $this->askSupportQuestion($step, $customer),
            StepKind::ProbeRequest => throw new LogicException('A probe step never reaches an ordinary session — see runProberSession().'),
        };
    }

    /**
     * @param  list<Listing>  $listings
     */
    private function listingFor(VisitStep $step, array $listings): Listing
    {
        return $listings[$step->listingSlot ?? 0];
    }

    /**
     * @param  list<Listing>  $listings
     */
    private function recordListingView(VisitStep $step, Customer $customer, array $listings, Analytics $analytics): void
    {
        $listing = $this->listingFor($step, $listings);

        $analytics->recordEvent(AnalyticsEvent::forListing(
            AnalyticsEventName::ListingView,
            $listing->id,
            $customer->id,
            $step->at,
            ListingViewCollapse::dedupeKey($listing->id, $customer->id, $step->at),
        ));
    }

    /**
     * @param  list<Listing>  $listings
     */
    private function toggleFavorite(VisitStep $step, Customer $customer, array $listings): void
    {
        app(ToggleFavorite::class)($customer, $this->listingFor($step, $listings), $step->at);
    }

    /**
     * @param  list<Listing>  $listings
     */
    private function addToCart(VisitStep $step, Customer $customer, array $listings): void
    {
        app(AddToCart::class)($this->cartFor($customer), $this->listingFor($step, $listings), 1, $step->at);
    }

    private function openCheckout(VisitStep $step, Customer $customer, Analytics $analytics): void
    {
        $cart = $this->cartFor($customer);
        /** @var list<string> $listingIds */
        $listingIds = $cart->items->pluck('listing_id')->unique()->values()->all();

        $analytics->recordEvent(AnalyticsEvent::forCart(
            AnalyticsEventName::CheckoutOpen,
            $cart->id,
            $cart->customer_id,
            $step->at,
            ['listing_ids' => $listingIds],
        ));
    }

    private function placeOrder(VisitStep $step, Customer $customer): void
    {
        $cart = $this->cartFor($customer);

        if ($cart->items()->doesntExist()) {
            return;
        }

        $purchaser = Purchaser::onAccount($customer->id, $customer->email, $customer->email_verified_at?->toDateTimeImmutable());

        $this->currentOrder = app(PlaceOrder::class)($cart, $purchaser, $this->shippingAddressFor($customer), $step->at);
    }

    private function payOrder(VisitStep $step): void
    {
        if ($this->currentOrder === null) {
            return;
        }

        app(FinalizeOrder::class)($this->currentOrder, self::APPROVED_CARD, $step->at);
    }

    private function cancelOrder(VisitStep $step): void
    {
        if ($this->currentOrder === null) {
            return;
        }

        app(CancelOrder::class)($this->currentOrder, $step->at);
    }

    /**
     * @param  list<Listing>  $listings
     */
    private function askListingQuestion(VisitStep $step, Customer $customer, array $listings): void
    {
        $listing = $this->listingFor($step, $listings);
        $question = self::LISTING_QUESTIONS[$this->pick(self::LISTING_QUESTIONS, $listing->id.$customer->id)];

        app(OpenThread::class)(
            ThreadOpening::listingQuestion($listing->seller_id, $customer->id, $listing->id, ThreadTitle::fromBody($question)),
            $customer,
            MessageBody::of($question),
            $step->at,
        );
    }

    private function askSupportQuestion(VisitStep $step, Customer $customer): void
    {
        $question = self::SUPPORT_QUESTIONS[$this->pick(self::SUPPORT_QUESTIONS, $customer->id.$step->at->format('U'))];

        app(OpenThread::class)(
            ThreadOpening::adminCustomer($customer->id, ThreadTitle::of('A question about my order')),
            $customer,
            MessageBody::of($question),
            $step->at,
        );
    }

    /**
     * @param  list<string>  $pool
     */
    private function pick(array $pool, string $key): int
    {
        return crc32($key) % count($pool);
    }

    private function cartFor(Customer $customer): Cart
    {
        return Cart::query()->firstOrCreate(['customer_id' => $customer->id]);
    }

    private function shippingAddressFor(Customer $customer): ShippingAddress
    {
        return ShippingAddress::to(
            name: $customer->name ?? 'Customer',
            line1: '4 Privet Drive',
            line2: null,
            city: 'Little Whinging',
            region: 'Surrey',
            postalCode: 'GU4 7XX',
            country: 'GB',
        );
    }

    /**
     * @param  list<Seller>  $sellers
     */
    private function runListingCreation(NewListingStep $creation, array $sellers): void
    {
        $seller = $sellers[$creation->sellerSlot];
        $template = ListingTemplates::all()[$creation->templateIndex];
        $category = Category::where('name', $template['category'])->sole();

        $listing = app(CreateListing::class)($seller, ListingDraft::of(
            $template['title'],
            $template['description'],
            $template['dimensions'],
            Money::fromCents($template['price_cents']),
            $template['quantity'],
            categoryId: $category->id,
        ));
        $listing->forceFill(['created_at' => $creation->createdAt, 'updated_at' => $creation->createdAt])->save();

        $this->attributeMedium($listing, $template['medium']);

        $listing->changeStatusTo(ListingStatus::ForSale);
        $listing->forceFill(['updated_at' => $creation->publishedAt])->save();
    }

    private function attributeMedium(Listing $listing, string $medium): void
    {
        $property = Property::where('name', 'Medium')->sole();
        $label = self::MEDIUM_LABEL_OVERRIDES[$medium] ?? ucfirst($medium);

        ListingAttribute::create([
            'listing_id' => $listing->id,
            'seller_id' => $listing->seller_id,
            'property_id' => $property->id,
            'property_value_id' => $property->values()->where('label', $label)->sole()->id,
        ]);
    }

    /**
     * Ships, and sometimes delivers, a majority of the fulfillments a paid
     * order created — enough left `awaiting_shipment` that the seller
     * portal still has something to act on. Timing is drawn from a hash of
     * the fulfillment's own id rather than {@see \App\Domain\Seeding\Lcg}:
     * this runs once, after every session, against rows the plan itself
     * never named, so it owes the plan no determinism of its own — only
     * that it never schedules a moment past `$now`.
     */
    private function runFulfillments(DateTimeImmutable $now): void
    {
        $fulfillments = Fulfillment::query()
            ->where('status', FulfillmentStatus::AwaitingShipment)
            ->with('order')
            ->get();

        foreach ($fulfillments as $fulfillment) {
            $this->maybeShip($fulfillment, $now);
        }
    }

    private function maybeShip(Fulfillment $fulfillment, DateTimeImmutable $now): void
    {
        if (crc32($fulfillment->id.'ship') % 100 >= 75) {
            return;
        }

        $finalizedAt = $fulfillment->order->finalized_at?->toDateTimeImmutable();

        if ($finalizedAt === null) {
            return;
        }

        $shippedAt = $finalizedAt->modify('+'.(1 + crc32($fulfillment->id.'ship-delay') % 3).' days');

        if ($shippedAt > $now) {
            return;
        }

        try {
            app(MarkShipped::class)($fulfillment, 'Owl Post', 'OWL-'.strtoupper(substr($fulfillment->id, -8)), $shippedAt);
        } catch (Throwable) {
            return;
        }

        $this->maybeDeliver($fulfillment, $shippedAt, $now);
    }

    private function maybeDeliver(Fulfillment $fulfillment, DateTimeImmutable $shippedAt, DateTimeImmutable $now): void
    {
        if (crc32($fulfillment->id.'deliver') % 100 >= 70) {
            return;
        }

        $deliveredAt = $shippedAt->modify('+'.(2 + crc32($fulfillment->id.'deliver-delay') % 4).' days');

        if ($deliveredAt > $now) {
            return;
        }

        try {
            app(ConfirmDelivered::class)($fulfillment, $deliveredAt);
        } catch (Throwable) {
            // Left shipped rather than delivered.
        }
    }

    /**
     * `payouts:run --as-of` once for every week the window spans, oldest
     * first — idempotent, so a week this loop's rough weekly spacing
     * revisits pays nothing a second time.
     */
    private function runPayouts(DateTimeImmutable $now, int $days): void
    {
        for ($weeksAgo = intdiv($days, 7) + 1; $weeksAgo >= 0; $weeksAgo--) {
            Artisan::call('payouts:run', ['--as-of' => $now->modify("-{$weeksAgo} weeks")->format('Y-m-d')]);
        }
    }

    private function report(int $customersBefore, int $ordersBefore): void
    {
        $this->info('Customers created: '.(Customer::query()->count() - $customersBefore));
        $this->info('Orders placed: '.(Order::query()->count() - $ordersBefore));
        $this->info('Analytics events: '.DB::connection('analytics')->table('analytics_events')->count());
        $this->info('Analytics visits: '.DB::connection('analytics')->table('analytics_visits')->count());
    }
}
