<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Store\StoreLinkKind;
use App\Domain\Store\StoreSectionKind;
use App\Domain\Store\StoreSlug as StoreSlugRule;
use App\Models\Listing;
use App\Models\Seller;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use App\Models\StoreSlug;
use DateTimeImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * A published store for every seeded seller: a tagline, where they work, a
 * story, and a gallery drawn from the photos already on their listings —
 * each one copied onto the store's own path, so a store picture and a
 * listing picture never share a file. Runs after the listing image seeder
 * and guards per seller, so it also lands on a database that already holds
 * some stores.
 */
class StoreProfileSeeder extends Seeder
{
    /**
     * The directory a store's own pictures live under, matching
     * {@see \App\Actions\Store\AddStoreImage}'s own upload directory.
     */
    private const string DIRECTORY = 'stores';

    /**
     * How many of a seller's listing photos become the store's pictures:
     * a portrait, a cover, and the gallery.
     */
    private const int PICTURES_PER_STORE = 6;

    private const int GALLERY_SIZE = 4;

    /**
     * Every seeded store, keyed by its seller's email.
     *
     * @var array<string, array{tagline: string, location: string, heading: string, story: string, gallery: string, website: string, instagram: string}>
     */
    private const STORES = [
        SellerSeeder::MOLLY_EMAIL => [
            'tagline' => 'Knitted, thrown, and carved at the Burrow',
            'location' => 'Ottery St Catchpole, Devon',
            'heading' => 'How the Burrow makes things',
            'story' => <<<'TEXT'
            Everything here is made in the kitchen, the shed, or the orchard at the Burrow. The knitting happens by the fire in winter; the pots come off a wheel that used to be a spare bicycle; the carving is done at the orchard gate on long evenings.

            I started selling when the house got too full of the things I make for the children. Each piece is one of one unless the listing says otherwise, and I pack every parcel myself.

            If you have a question about a piece, ask. I answer the same day.
            TEXT,
            'gallery' => 'Around the house',
            'website' => 'https://theburrow.example',
            'instagram' => '@theburrowcraftworks',
        ],
        SellerSeeder::DEAN_EMAIL => [
            'tagline' => 'Drawings and paintings of the places I remember',
            'location' => 'West London',
            'heading' => 'Working from memory',
            'story' => <<<'TEXT'
            I draw the corridors, the pitches, and the common rooms the way they looked at the hour I remember them best. Charcoal first, then paint if the light in the sketch holds up.

            Prints are made in small runs on cotton rag. Originals are one of one and go out flat between boards.
            TEXT,
            'gallery' => 'From the sketchbooks',
            'website' => 'https://deanthomas.example',
            'instagram' => '@deanthomasstudio',
        ],
        SellerSeeder::SYBILL_EMAIL => [
            'tagline' => 'Vessels, orbs, and things that catch the light',
            'location' => 'The North Tower',
            'heading' => 'From the tower',
            'story' => <<<'TEXT'
            The tower is cold and the light comes in sideways, which turns out to be the right light for glazing. Everything is thrown, dried slowly, and fired twice.

            Tea things get the most attention here. A cup you hold every morning ought to be worth holding.
            TEXT,
            'gallery' => 'On the shelves',
            'website' => 'https://towerstudio.example',
            'instagram' => '@trelawneytower',
        ],
        SellerSeeder::COLIN_EMAIL => [
            'tagline' => 'Photographs, developed the slow way',
            'location' => 'Hogsmeade',
            'heading' => 'The darkroom under the stairs',
            'story' => <<<'TEXT'
            I shoot on film and develop everything myself in a room that used to be a cupboard. Wet plate for portraits, black and white for everything else.

            Every print is made to order and signed on the back. Tell me the size you want and I will tell you whether the negative can carry it.
            TEXT,
            'gallery' => 'Recent prints',
            'website' => 'https://creeveycamera.example',
            'instagram' => '@creeveycameraworks',
        ],
        WizardingSellerSeeder::NEVILLE_EMAIL => [
            'tagline' => 'Plants, cuttings, and the pots they live in',
            'location' => 'The greenhouses',
            'heading' => 'Grown, not ordered in',
            'story' => <<<'TEXT'
            Every cutting here was taken from a plant I grew. Nothing ships until it has rooted and held its leaves for a fortnight.

            Care notes go in the box. If a plant arrives unhappy, write to me and I will make it right.
            TEXT,
            'gallery' => 'In the greenhouse',
            'website' => 'https://greenhousethree.example',
            'instagram' => '@nevillegrows',
        ],
        WizardingSellerSeeder::LUNA_EMAIL => [
            'tagline' => 'Odd jewellery and things worth looking twice at',
            'location' => 'Near Ottery St Catchpole',
            'heading' => 'Why the things here look like that',
            'story' => <<<'TEXT'
            I make what I would wear. Corks, plums, glass, and whatever the beach gives up in February.

            Nothing here is a copy of anything else, which is why the photographs are of the actual piece you will get.
            TEXT,
            'gallery' => 'Odds and ends',
            'website' => 'https://spectrespecs.example',
            'instagram' => '@lunalovegoodmakes',
        ],
        ConfiguratorArchetypeSeeder::EMAIL => [
            'tagline' => 'Joke goods, made properly',
            'location' => 'Diagon Alley',
            'heading' => 'Made in the back room',
            'story' => <<<'TEXT'
            Everything on this page is assembled by hand above the shop. The paper is printed here, the tins are filled here, and the boxes are packed by whoever is nearest the door.

            Choose the size, the colour, and the wording, and it is made to that order. Nothing is sitting on a shelf waiting.
            TEXT,
            'gallery' => 'Above the shop',
            'website' => 'https://wizardwheezes.example',
            'instagram' => '@weasleyswizardwheezes',
        ],
    ];

    public function run(): void
    {
        $publishedAt = new DateTimeImmutable('2026-06-15 09:00:00');

        foreach (self::STORES as $email => $copy) {
            $seller = Seller::where('email', $email)->first();

            if (! $seller instanceof Seller || $seller->storeProfile()->exists()) {
                continue;
            }

            $this->buildStore($seller, $copy, $publishedAt);
        }
    }

    /**
     * @param  array{tagline: string, location: string, heading: string, story: string, gallery: string, website: string, instagram: string}  $copy
     */
    private function buildStore(Seller $seller, array $copy, DateTimeImmutable $publishedAt): void
    {
        $name = $seller->displayName();

        $profile = StoreProfile::create([
            'seller_id' => $seller->id,
            'slug' => StoreSlugRule::firstFree($name, $this->slugsTaken()),
            'name' => $name,
            'tagline' => $copy['tagline'],
            'location' => $copy['location'],
            'published_at' => $publishedAt,
        ]);

        StoreSlug::create(['store_profile_id' => $profile->id, 'slug' => $profile->slug]);

        $pictures = $this->pictures($profile, $seller);

        $profile->update([
            'portrait_image_id' => $pictures[0]->id ?? null,
            'cover_image_id' => $pictures[1]->id ?? $pictures[0]->id ?? null,
        ]);

        $this->story($profile, $copy);
        $this->gallery($profile, $copy['gallery'], array_slice($pictures, 2, self::GALLERY_SIZE));
        $this->links($profile, $copy);
    }

    /**
     * The store's pictures: copies of the photos the seller's listings
     * already show, one file per picture under the store's own directory —
     * a store picture never names a listing's file.
     *
     * @return list<StoreImage>
     */
    private function pictures(StoreProfile $profile, Seller $seller): array
    {
        $listingPaths = [];

        foreach (Listing::query()->where('seller_id', $seller->id)->with('images')->get() as $listing) {
            foreach ($listing->images as $image) {
                if (! in_array($image->path, $listingPaths, true)) {
                    $listingPaths[] = $image->path;
                }
            }
        }

        $pictures = [];

        foreach (array_slice($listingPaths, 0, self::PICTURES_PER_STORE) as $listingPath) {
            // Named by the store's own id, so two stores drawing on
            // listing photos that happen to share a filename never copy
            // onto the same store path.
            $storePath = self::DIRECTORY.'/'.$profile->id.'-'.basename($listingPath);
            Storage::disk('public')->copy($listingPath, $storePath);

            $pictures[] = StoreImage::create([
                'store_profile_id' => $profile->id,
                'seller_id' => $seller->id,
                'path' => $storePath,
            ]);
        }

        return $pictures;
    }

    /**
     * @param  array{heading: string, story: string}  $copy
     */
    private function story(StoreProfile $profile, array $copy): void
    {
        StoreSection::create([
            'store_profile_id' => $profile->id,
            'kind' => StoreSectionKind::Story,
            'position' => 0,
            'heading' => $copy['heading'],
            'body' => $copy['story'],
        ]);
    }

    /**
     * @param  list<StoreImage>  $pictures
     */
    private function gallery(StoreProfile $profile, string $heading, array $pictures): void
    {
        if ($pictures === []) {
            return;
        }

        $section = StoreSection::create([
            'store_profile_id' => $profile->id,
            'kind' => StoreSectionKind::Gallery,
            'position' => 1,
            'heading' => $heading,
        ]);

        foreach ($pictures as $position => $picture) {
            $section->sectionImages()->create(['store_image_id' => $picture->id, 'position' => $position]);
        }
    }

    /**
     * @param  array{website: string, instagram: string}  $copy
     */
    private function links(StoreProfile $profile, array $copy): void
    {
        $profile->links()->create(['kind' => StoreLinkKind::Website, 'url' => $copy['website'], 'position' => 0]);
        $profile->links()->create(['kind' => StoreLinkKind::Instagram, 'url' => $copy['instagram'], 'position' => 1]);
    }

    /**
     * @return list<string>
     */
    private function slugsTaken(): array
    {
        /** @var list<string> $slugs */
        $slugs = array_values(StoreSlug::query()->pluck('slug')->all());

        return $slugs;
    }
}
