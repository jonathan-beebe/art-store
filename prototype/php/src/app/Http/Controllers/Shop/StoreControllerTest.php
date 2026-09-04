<?php

declare(strict_types=1);

use App\Actions\Store\RenameStoreSlug;
use App\Actions\Store\StartStore;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Listings\ListingStatus;
use App\Domain\Store\StoreSectionKind;
use App\Domain\Store\StoreViewCollapse;
use App\Models\Listing;
use App\Models\Seller;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use Illuminate\Support\Facades\DB;

/**
 * A published store at `the-burrow-craftworks` with its seller.
 *
 * @return array{Seller, StoreProfile}
 */
$publishedStore = function (): array {
    $seller = Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']);
    $profile = app(StartStore::class)($seller);
    $profile->update([
        'published_at' => now(),
        'tagline' => 'Knitted, thrown, and carved at the Burrow',
        'location' => 'Ottery St Catchpole, Devon',
    ]);

    return [$seller, $profile->refresh()];
};

it('shows a published store to anyone', function () use ($publishedStore): void {
    [, $profile] = $publishedStore();

    $this->get('/s/the-burrow-craftworks')
        ->assertOk()
        ->assertSee('The Burrow Craftworks')
        ->assertSee('Knitted, thrown, and carved at the Burrow')
        ->assertSee('Ottery St Catchpole, Devon');

    expect($profile->slug)->toBe('the-burrow-craftworks');
});

it('renders the sections a seller wrote, in order', function () use ($publishedStore): void {
    [, $profile] = $publishedStore();
    StoreSection::factory()->create([
        'store_profile_id' => $profile->id,
        'position' => 0,
        'kind' => StoreSectionKind::Story,
        'heading' => 'How the Burrow makes things',
        'body' => 'Everything here is made in the kitchen.',
    ]);

    $this->get('/s/the-burrow-craftworks')
        ->assertOk()
        ->assertSee('How the Burrow makes things')
        ->assertSee('Everything here is made in the kitchen.');
});

it('shows the maker\'s storefront listings and hides the rest', function () use ($publishedStore): void {
    [$seller] = $publishedStore();
    $forSale = Listing::factory()->create(['seller_id' => $seller->id, 'title' => 'Burrow Kitchen Tea Bowl', 'status' => ListingStatus::ForSale]);
    Listing::factory()->create(['seller_id' => $seller->id, 'title' => 'A Draft Nobody Sees', 'status' => ListingStatus::Draft]);
    Listing::factory()->create(['seller_id' => $seller->id, 'title' => 'An Archived Piece', 'status' => ListingStatus::Archived]);

    $this->get('/s/the-burrow-craftworks')
        ->assertOk()
        ->assertSee($forSale->title)
        ->assertDontSee('A Draft Nobody Sees')
        ->assertDontSee('An Archived Piece');
});

it('leaves another seller\'s work off the page', function () use ($publishedStore): void {
    $publishedStore();
    Listing::factory()->create(['title' => 'Nine Owls', 'status' => ListingStatus::ForSale]);

    $this->get('/s/the-burrow-craftworks')->assertOk()->assertDontSee('Nine Owls');
});

it('carries the page meta a link preview reads', function () use ($publishedStore): void {
    [, $profile] = $publishedStore();
    $cover = StoreImage::factory()->create(['store_profile_id' => $profile->id, 'path' => 'stores/orchard.jpg']);
    $profile->update(['cover_image_id' => $cover->id]);

    $this->get('/s/the-burrow-craftworks')
        ->assertOk()
        ->assertSee('<title>The Burrow Craftworks — Art Store</title>', false)
        ->assertSee('<meta name="description" content="Knitted, thrown, and carved at the Burrow">', false)
        ->assertSee('/storage/stores/orchard.jpg', false);
});

it('answers 404 for an address no store has held', function (): void {
    $this->get('/s/nine-owls')->assertNotFound();
});

it('forwards permanently from an address the store recently left', function () use ($publishedStore): void {
    [, $profile] = $publishedStore();
    app(RenameStoreSlug::class)($profile, 'burrow-works', now()->toDateTimeImmutable());

    $this->get('/s/the-burrow-craftworks')
        ->assertStatus(301)
        ->assertRedirect(route('shop.store', ['slug' => 'burrow-works']));
});

it('answers 404 for an address retired past the forwarding window', function () use ($publishedStore): void {
    [, $profile] = $publishedStore();
    app(RenameStoreSlug::class)($profile, 'burrow-works', now()->subDays(31)->toDateTimeImmutable());

    $this->get('/s/the-burrow-craftworks')->assertNotFound();
});

it('answers 404 for a hidden store', function (): void {
    $seller = Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']);
    app(StartStore::class)($seller);

    $this->get('/s/the-burrow-craftworks')->assertNotFound();
});

it('shows a hidden store to its own seller with a banner', function (): void {
    $seller = Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']);
    app(StartStore::class)($seller);

    $this->actingAs($seller, 'seller')
        ->get('/s/the-burrow-craftworks')
        ->assertOk()
        ->assertSee('This store is hidden.');
});

it('answers 404 for another seller\'s hidden store', function (): void {
    $seller = Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']);
    app(StartStore::class)($seller);

    $this->actingAs(Seller::factory()->create(), 'seller')
        ->get('/s/the-burrow-craftworks')
        ->assertNotFound();
});

it('records the view with the store as its subject, collapsed to the hour', function () use ($publishedStore): void {
    [, $profile] = $publishedStore();

    $this->get('/s/the-burrow-craftworks')->assertOk();
    app(Analytics::class)->flush();

    $row = DB::connection('analytics')->table('analytics_events')->where('name', 'store.view')->sole();

    expect($row->subject_type)->toBe('store')
        ->and($row->subject_id)->toBe($profile->id)
        ->and($row->dedupe_key)->toStartWith("store:{$profile->id}:customer:");
});

it('writes one row however often the same visitor reloads inside the hour', function () use ($publishedStore): void {
    [, $profile] = $publishedStore();
    $customer = $this->verifiedCustomer();
    $now = now()->toDateTimeImmutable();
    $analytics = app(Analytics::class);

    foreach (range(1, 3) as $ignored) {
        $analytics->recordEvent(AnalyticsEvent::forStore(
            AnalyticsEventName::StoreView,
            $profile->id,
            $customer->id,
            $now,
            StoreViewCollapse::dedupeKey($profile->id, $customer->id, $now),
        ));
    }
    $analytics->flush();

    expect(DB::connection('analytics')->table('analytics_events')->where('name', 'store.view')->count())->toBe(1);
});

it('records nothing when a seller previews their own hidden store', function (): void {
    $seller = Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']);
    app(StartStore::class)($seller);

    $this->actingAs($seller, 'seller')->get('/s/the-burrow-craftworks')->assertOk();
    app(Analytics::class)->flush();

    expect(DB::connection('analytics')->table('analytics_events')->where('name', 'store.view')->count())->toBe(0);
});

it('names the maker on a listing card as the way into their store', function () use ($publishedStore): void {
    [$seller, $profile] = $publishedStore();
    Listing::factory()->create(['seller_id' => $seller->id, 'status' => ListingStatus::ForSale]);

    $this->get('/')
        ->assertOk()
        ->assertSee(route('shop.store', ['slug' => $profile->slug]), false);
});

it('leaves a card naming a hidden store as plain text', function (): void {
    $seller = Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']);
    $profile = app(StartStore::class)($seller);
    Listing::factory()->create(['seller_id' => $seller->id, 'status' => ListingStatus::ForSale]);

    $this->get('/')
        ->assertOk()
        ->assertSee('The Burrow Craftworks')
        ->assertDontSee(route('shop.store', ['slug' => $profile->slug]), false);
});

it('leaves a card naming a seller with no store as plain text', function (): void {
    $seller = Seller::factory()->create(['shop_name' => 'Nine Owls']);
    Listing::factory()->create(['seller_id' => $seller->id, 'status' => ListingStatus::ForSale]);

    $this->get('/')->assertOk()->assertSee('Nine Owls');
});

it('names the maker on a listing page as the way into their store', function () use ($publishedStore): void {
    [$seller, $profile] = $publishedStore();
    $listing = Listing::factory()->create(['seller_id' => $seller->id, 'status' => ListingStatus::ForSale, 'slug' => 'burrow-kitchen-tea-bowl']);

    $this->get("/art/{$listing->slug}")
        ->assertOk()
        ->assertSee(route('shop.store', ['slug' => $profile->slug]), false);
});

it('answers 404 at the old address of a store that has since been hidden', function () use ($publishedStore): void {
    [, $profile] = $publishedStore();
    app(RenameStoreSlug::class)($profile, 'burrow-works', now()->toDateTimeImmutable());
    $profile->update(['published_at' => null]);

    $this->get('/s/the-burrow-craftworks')->assertNotFound();
});

it('leaves the Open Graph tags off a page that asks for none', function (): void {
    $this->get('/')->assertOk()->assertDontSee('og:title', false);
});
