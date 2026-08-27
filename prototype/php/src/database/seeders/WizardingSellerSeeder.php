<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Listings\CreateListing;
use App\Domain\Listings\ListingDraft;
use App\Domain\Listings\ListingStatus;
use App\Domain\Money\Money;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\Property;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Database\Seeder;

/**
 * Two more verified sellers a reviewer can sign in as, each with a live
 * catalog. Runs after the demo seeders and guards on its own first seller's
 * email, so it also lands on a database that already holds the demo data.
 */
class WizardingSellerSeeder extends Seeder
{
    public const NEVILLE_EMAIL = 'neville@example.com';

    public const LUNA_EMAIL = 'luna@example.com';

    public function run(): void
    {
        if (Seller::where('email', self::NEVILLE_EMAIL)->exists()) {
            return;
        }

        $verifiedAt = new DateTimeImmutable('2026-08-20 00:00:00');
        $createListing = app(CreateListing::class);

        foreach ($this->sellers() as $entry) {
            $seller = Seller::create([
                'email' => $entry['email'],
                'name' => $entry['name'],
                'shop_name' => $entry['shop_name'],
                'email_verified_at' => $verifiedAt,
            ]);

            foreach ($entry['listings'] as $listing) {
                $category = Category::where('name', $listing['category'])->sole();

                $created = $createListing($seller, ListingDraft::of(
                    $listing['title'],
                    $listing['description'],
                    $listing['dimensions'],
                    Money::fromCents($listing['price_cents']),
                    $listing['quantity'],
                    categoryId: $category->id,
                ))->changeStatusTo(ListingStatus::ForSale);

                $this->attributeMedium($created, $listing['medium']);
            }
        }
    }

    /**
     * @return list<array{email: string, name: string, shop_name: string, listings: list<array{title: string, medium: string, category: string, dimensions: string, price_cents: int, quantity: int, description: string}>}>
     */
    private function sellers(): array
    {
        return [
            [
                'email' => self::NEVILLE_EMAIL,
                'name' => 'Neville Longbottom',
                'shop_name' => 'Longbottom Botanicals',
                'listings' => [
                    $this->listing('Mimbulus Mimbletonia, Potted', 'plant', 'Home Goods', '8 x 5 x 5 in', 9500, 1,
                        'A rare grey cactus-like specimen, its surface moving gently as it breathes. Raised from a cutting my great uncle Algie brought back from Assyria. Ships in its own terracotta pot with a full care sheet — do not prod the boils.'),
                    $this->listing('Flitterbloom Cutting, Rooted', 'plant', 'Home Goods', '12 in tendrils', 4500, 3,
                        'A rooted Flitterbloom cutting with long swaying tendrils, often mistaken for Devil’s Snare but entirely harmless. Thrives in a bright window and asks for little beyond weekly water. Grown in Greenhouse Three from healthy parent stock.'),
                    $this->listing('Puffapod Seed Collection', 'plant', 'Home Goods', 'tin of 20 pods', 2500, 6,
                        'Twenty plump pink Puffapod pods in a lidded tin. Drop one anywhere and it bursts into flower on the spot, so sow them where you mean it. Harvested by hand at full ripeness this season.'),
                    $this->listing('Bouncing Bulb, Established', 'plant', 'Home Goods', '10 x 7 x 7 in', 6000, 1,
                        'A well-established Bouncing Bulb, repotted twice and calm for its kind. Keeps to modest hops once it settles into a routine. Sturdy gloves recommended at repotting time; it only wriggles when startled.'),
                ],
            ],
            [
                'email' => self::LUNA_EMAIL,
                'name' => 'Luna Lovegood',
                'shop_name' => 'Lovegood Curiosities',
                'listings' => [
                    $this->listing('The Quibbler, Back-Issue Bundle', 'publication', 'Stationery', '8.5 x 11 in, set of 5', 1200, 12,
                        'Five assorted back issues of The Quibbler, my father’s magazine, including the Crumple-Horned Snorkack expedition special. Some covers print upside down on purpose. Each bundle is different, which is rather the point.'),
                    $this->listing('Spectrespecs', 'curio', 'Home Goods', '6 x 2 x 1 in', 3500, 5,
                        'Pink-and-blue paper spectacles that make Wrackspurts visible as they drift out of people’s ears. Very useful for working out why your thinking has gone fuzzy. Free with some issues of The Quibbler, but these are the sturdier keepsake edition.'),
                    $this->listing('Butterbeer Cork Necklace', 'jewelry', 'Jewelry', '18 in cord', 1800, 4,
                        'A necklace of butterbeer corks strung on waxed cord, worn to keep the Nargles away. Each cork is collected personally and threaded by hand. The Nargles have never once bothered me while wearing it.'),
                    $this->listing('Dirigible Plum Earrings', 'jewelry', 'Jewelry', '2 in drop', 2200, 3,
                        'A pair of bright orange dirigible plum earrings, carved and painted to float just slightly on a breeze. The plums grow beside our front door and enhance the ability to accept the extraordinary. Hooks are plain silver.'),
                ],
            ],
        ];
    }

    /**
     * @return array{title: string, medium: string, category: string, dimensions: string, price_cents: int, quantity: int, description: string}
     */
    private function listing(
        string $title,
        string $medium,
        string $category,
        string $dimensions,
        int $priceCents,
        int $quantity,
        string $description,
    ): array {
        return [
            'title' => $title,
            'medium' => $medium,
            'category' => $category,
            'dimensions' => $dimensions,
            'price_cents' => $priceCents,
            'quantity' => $quantity,
            'description' => $description,
        ];
    }

    /**
     * The Medium attribute matching this listing's legacy medium string —
     * every one of this seeder's values title-cases straight onto a
     * `TaxonomySeeder` label (plant → Plant, jewelry → Jewelry, …).
     */
    private function attributeMedium(Listing $listing, string $legacyMedium): void
    {
        $property = Property::where('name', 'Medium')->sole();

        ListingAttribute::create([
            'listing_id' => $listing->id,
            'property_id' => $property->id,
            'property_value_id' => $property->values()->where('label', ucfirst($legacyMedium))->sole()->id,
        ]);
    }
}
