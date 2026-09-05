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
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * 24 for_sale listings across six media, three drafts, and two sold-out
 * pieces. Three of the for_sale listings start at quantity 2 so
 * OrderHistorySeeder can sell one unit of each and leave them on the
 * storefront. Every listing is created through `CreateListing`, the same
 * action a seller's form submits to, then moved to its target status through
 * the model's own transitions.
 */
class ListingSeeder extends Seeder
{
    /**
     * The category each of this seeder's six legacy media fits under —
     * every one of them hosts a `TaxonomySeeder` Medium grant.
     *
     * @var array<string, string>
     */
    private const CATEGORY_BY_MEDIUM = [
        'painting' => 'Art',
        'print' => 'Art',
        'photography' => 'Art',
        'ceramic' => 'Home Goods',
        'textile' => 'Home Goods',
        'sculpture' => 'Home Goods',
    ];

    /**
     * The one legacy medium string that does not title-case straight onto its
     * `TaxonomySeeder` Medium label.
     */
    private const MEDIUM_LABEL_OVERRIDES = [
        'photography' => 'Photograph',
    ];

    /**
     * The one listing that exercises Home Goods' multivalued Medium grant
     * (FEAT-031): a sculpture carved from a reclaimed beam is genuinely both
     * Sculpture and Wood at once, matching this title to a second Medium
     * value beyond the one every listing gets automatically. It also
     * demonstrates the no-choice case for a specific-type property
     * (FEAT-032, §2.1 "Attribute altitude"): a fixed-species piece states
     * Wood Species as an attribute.
     */
    private const MULTIVALUED_MEDIUM_TITLE = 'Garden Gnome in Reclaimed Oak';

    private const ADDITIONAL_MEDIUM_VALUE = 'Wood';

    private const FIXED_WOOD_SPECIES_VALUE = 'Oak';

    public function run(): void
    {
        $sellers = Seller::query()->get()->keyBy('email');
        $createListing = app(CreateListing::class);

        foreach ($this->listings() as $entry) {
            $seller = $sellers->get($entry['seller']) ?? throw new RuntimeException("No seller seeded for {$entry['seller']}.");
            $category = Category::where('name', self::CATEGORY_BY_MEDIUM[$entry['medium']])->sole();

            $listing = $createListing($seller, ListingDraft::of(
                $entry['title'],
                $entry['description'],
                $entry['dimensions'],
                Money::fromCents($entry['price_cents']),
                $entry['quantity'],
                categoryId: $category->id,
            ));

            $this->advance($listing, $entry['status']);
            $this->attributeMedium($listing, self::MEDIUM_LABEL_OVERRIDES[$entry['medium']] ?? ucfirst($entry['medium']));

            // Home Goods' Medium grant is multivalued (TaxonomySeeder) — this
            // is the one listing that demonstrates it, carrying Sculpture
            // (above) and Wood at once.
            if ($entry['title'] === self::MULTIVALUED_MEDIUM_TITLE) {
                $this->attributeMedium($listing, self::ADDITIONAL_MEDIUM_VALUE);
                $this->attribute($listing, 'Wood Species', self::FIXED_WOOD_SPECIES_VALUE);
            }
        }
    }

    /**
     * A Medium attribute matching the given label.
     */
    private function attributeMedium(Listing $listing, string $label): void
    {
        $this->attribute($listing, 'Medium', $label);
    }

    /**
     * Writes one listing_attributes row directly — reference data, the same
     * way {@see TaxonomySeeder} writes its own rows, bypassing the
     * seller-facing {@see \App\Actions\Configurator\SetListingAttributes}.
     */
    private function attribute(Listing $listing, string $propertyName, string $label): void
    {
        $property = Property::where('name', $propertyName)->sole();

        ListingAttribute::create([
            'listing_id' => $listing->id,
            'seller_id' => $listing->seller_id,
            'property_id' => $property->id,
            'property_value_id' => $property->values()->where('label', $label)->sole()->id,
        ]);
    }

    private function advance(Listing $listing, ListingStatus $target): void
    {
        match ($target) {
            ListingStatus::Draft => null,
            ListingStatus::ForSale => $listing->changeStatusTo(ListingStatus::ForSale),
            // A listing reaches the storefront before it can sell out: put it
            // up for sale, then sell the stock it was created with.
            ListingStatus::Sold => $listing->changeStatusTo(ListingStatus::ForSale)->sell($listing->quantity ?? throw new RuntimeException('A seeded sold-out listing always starts with a fixed quantity.')),
            ListingStatus::Archived => $listing->changeStatusTo(ListingStatus::Archived),
        };
    }

    /**
     * @return list<array{seller: string, title: string, medium: string, dimensions: string, price_cents: int, description: string, status: ListingStatus, quantity: int}>
     */
    private function listings(): array
    {
        return [
            $this->entry(SellerSeeder::MOLLY_EMAIL, 'The Burrow at Dusk', 'painting', '24 x 36 in', 68000,
                'The crooked silhouette of the Burrow leans into a violet evening sky, one window lit in the kitchen. Palette-knife strokes carry the improbable stack of upper storeys. Painted from the orchard gate over three summer evenings.'),
            $this->entry(SellerSeeder::DEAN_EMAIL, 'Gryffindor Common Room, Late Morning', 'painting', '18 x 24 in', 42000,
                'Sun crosses a worn armchair by the fire, catching an abandoned chess set and a half-rolled essay. Loose brushwork keeps the scene from feeling staged. Part of an ongoing series on quiet corners of the castle.',
                quantity: 2),
            $this->entry(SellerSeeder::SYBILL_EMAIL, 'Lavender Fields from the North Tower', 'painting', '30 x 40 in', 95000,
                'Rows of lavender recede toward the Forbidden Forest under a bruised summer sky, seen from the tower window. Thin glazes sit over a toned ground, so the underpainting shows through the purple. The composition arrived in a vision; the painting took a season.'),
            $this->entry(SellerSeeder::COLIN_EMAIL, 'Hogsmeade Fog, Early Shift', 'painting', '20 x 30 in', 76000,
                'The high street sits behind a scrim of morning fog, shopfronts barely distinct from the snow. A single lamp outside the Three Broomsticks anchors the composition. Reference photographs came from a week of dawn walks before the shops opened.'),

            $this->entry(SellerSeeder::MOLLY_EMAIL, 'Nine Owls', 'print', '16 x 20 in', 12000,
                'Nine owls in profile, carved in a single block and printed in three passes of grey ink. Each bird holds a different tilt of the head, drawn from a winter of post arriving at the kitchen window. Edition of thirty, hand-numbered.'),
            $this->entry(SellerSeeder::DEAN_EMAIL, 'Platform Nine and Three-Quarters', 'print', '18 x 24 in', 15000,
                'The platform rendered in four flat colors, the crowd reduced to silhouettes, trolleys, and one scarlet engine. Screenprinted by hand in small batches. Part of a set of journey prints made from first-of-September sketches.'),
            $this->entry(SellerSeeder::SYBILL_EMAIL, 'Tea Leaf Study', 'print', '11 x 14 in', 6000,
                'The bottom of a teacup printed in two risograph passes, sepia over a warm grey. The registration sits slightly loose on purpose, so the leaves refuse to settle into one reading. Whether you see the Grim is entirely your own affair.'),
            $this->entry(SellerSeeder::COLIN_EMAIL, 'Hogwarts Express, Night Crossing', 'print', '14 x 18 in', 22000,
                'The Express crosses the viaduct at night, the headlamp the only bright point on the plate. Deep bitten lines carry the dark, aquatint fills the sky. Printed on a hand press in an edition of twelve.'),

            $this->entry(SellerSeeder::MOLLY_EMAIL, 'Burrow Kitchen Tea Bowl', 'ceramic', '4 x 4 x 3 in', 8500,
                'A stoneware tea bowl fired with orchard-wood ash landing across the shoulder in a natural drip. The foot is trimmed thin and left unglazed to show the clay body. Thrown between batches of bread on a quiet Burrow morning.',
                quantity: 2),
            $this->entry(SellerSeeder::DEAN_EMAIL, 'Butterbeer Pitcher, Speckled Stoneware', 'ceramic', '9 x 6 x 6 in', 14000,
                'A pitcher thrown in a speckled stoneware clay, pulled handle attached while the body is still soft. The spout is cut for a clean pour with a proper head of foam. Glazed in a satin butterscotch that breaks over the throwing rings.'),
            $this->entry(SellerSeeder::SYBILL_EMAIL, 'Divination Tower Vase, Tall', 'ceramic', '14 x 6 x 6 in', 32000,
                'A tall thrown vase, fired unglazed in a wood kiln so ash and flame draw a map of portents across the surface. No two sides read the same, which is rather the point. Fourteen inches gives it enough height for a single branch or a full arrangement.'),
            $this->entry(SellerSeeder::COLIN_EMAIL, 'Great Hall Serving Bowl', 'ceramic', '12 x 12 x 4 in', 19500,
                'A wide serving bowl salt-glazed to an orange-peel texture, the rim left slightly irregular from the wheel. Food-safe and built for a crowded table rather than display. Fires to a warm amber wherever the flame reaches it directly.'),

            $this->entry(SellerSeeder::MOLLY_EMAIL, 'Knitted Letter Jumper, Wall Piece', 'textile', '36 x 48 in', 24000,
                'A hand-knitted jumper in deep maroon with a large gold initial, mounted flat as a wall piece. The letter is worked in intarsia, not stitched on after. Commissions take a month; December orders should allow for the Christmas rush.'),
            $this->entry(SellerSeeder::DEAN_EMAIL, 'House Scarf Throw, Scarlet and Gold', 'textile', '50 x 70 in', 32000,
                'A plain-weave throw in scarlet wool and a fine gold warp, woven on a floor loom over two weeks. Wide bands keep it a blanket rather than a costume piece. Fringe is hand-twisted at both ends.'),
            $this->entry(SellerSeeder::SYBILL_EMAIL, 'Patchwork Shawl Runner, Ochre', 'textile', '24 x 72 in', 18000,
                'A runner woven from strips of retired shawls in a range of ochre and rust, each with a history of draughty tower evenings. Every strip carries a trace of its previous life. Reversible, with a matching pattern on both sides.'),
            $this->entry(SellerSeeder::COLIN_EMAIL, 'Naturally Dyed Silk Scarf', 'textile', '18 x 72 in', 9500,
                "A silk habotai scarf dyed with onion skin and marigold from the greenhouse compost heap, giving a gradient from pale gold to deep amber. Hand-hemmed along all four edges. Each dye lot varies with the season's plant material."),

            $this->entry(SellerSeeder::MOLLY_EMAIL, 'Garden Gnome in Reclaimed Oak', 'sculpture', '22 x 8 x 8 in', 185000,
                'A garden gnome carved from a single piece of reclaimed oak beam, caught mid-scowl the moment before it bolts. The grain of the old beam runs through the torso like a seam. Finished with hand-rubbed oil; guaranteed not to bite.',
                quantity: 2),
            $this->entry(SellerSeeder::DEAN_EMAIL, 'Welded Steel Hippogriff', 'sculpture', '16 x 10 x 20 in', 96000,
                'A hippogriff built from welded steel plate and rod, the feathers suggested with cut sheet rather than modeled in detail. The finish is a raw steel patina, left to develop rust over time. Approach the sculpture however you like; it has never once demanded a bow.'),
            $this->entry(SellerSeeder::SYBILL_EMAIL, 'Cast Bronze Seeing Orb', 'sculpture', '10 x 6 x 6 in', 145000,
                'An orb and stand cast in bronze from a wax original, patinated to a deep green over brown. The surface holds the fine texture of the original carving, clouded the way a proper glass should be. Cast in a lost-wax edition of eight.'),
            $this->entry(SellerSeeder::COLIN_EMAIL, 'Standing Stones, Black Lake', 'sculpture', '30 x 12 x 12 in', 68000,
                'Four lakeshore stones stacked and pinned along a hidden steel rod, the balance point of each stone left visible. Stone comes from a single stretch of the Black Lake shore, chosen for color and grain across the set. Built for an outdoor garden setting.'),

            $this->entry(SellerSeeder::MOLLY_EMAIL, 'The Orchard at First Light', 'photography', '24 x 36 in', 45000,
                'The Burrow orchard photographed at first light, mist still sitting between the apple rows where the children practice Quidditch. Printed as an archival pigment print on cotton rag paper. Shot on medium-format film and scanned at high resolution.'),
            $this->entry(SellerSeeder::DEAN_EMAIL, 'Diagon Alley After Rain', 'photography', '20 x 30 in', 38000,
                'The alley after rain, shop signs doubled in the wet cobbles. A long exposure holds the blur of a single hurrying cloak. Printed in a limited run of fifteen.'),
            $this->entry(SellerSeeder::SYBILL_EMAIL, 'The Great Lake, Noon', 'photography', '30 x 40 in', 52000,
                "The lake under a noon sun, the horizon line barely visible between white water and white sky. A lone figure stands near the frame's edge for scale; the tentacle was not planned. Printed large to hold the flatness of the light."),
            $this->entry(SellerSeeder::COLIN_EMAIL, 'Portrait of a Gamekeeper', 'photography', '16 x 20 in', 29500,
                'The gamekeeper mid-task at the pumpkin patch, forge light from the hut catching the edge of a moleskin coat. Shot on black-and-white film and printed in a wet darkroom. Part of a portrait series on the castle grounds staff.'),

            $this->entry(SellerSeeder::DEAN_EMAIL, 'Quidditch Keeper, Charcoal Study', 'painting', '18 x 24 in', 15000,
                'A charcoal study of a keeper hanging off the left-most hoop, kept loose and unfinished. Working drawing for a larger match painting still in progress.',
                status: ListingStatus::Draft),
            $this->entry(SellerSeeder::SYBILL_EMAIL, 'Tasseled Shawl Sampler', 'textile', '20 x 20 in', 12000,
                'A test panel of waxed linen dyed in three tannin baths, made to check color before a full-size shawl. Not yet mounted or finished at the edges.',
                status: ListingStatus::Draft),
            $this->entry(SellerSeeder::COLIN_EMAIL, 'Glaze Test Tiles, Series 3', 'ceramic', '6 x 6 in each', 4000,
                'A set of glaze test tiles from the third round of a new ash glaze recipe. Kept as a reference rather than sold, listed here as a draft.',
                status: ListingStatus::Draft),

            $this->entry(SellerSeeder::MOLLY_EMAIL, 'Copper Cauldron Bowl', 'ceramic', '10 x 10 x 4 in', 22000,
                'A thrown bowl finished with a copper-oxide wash that fires to a mottled green and black, the shape borrowed from a favorite old cauldron. The last piece from a small batch fired in the spring.',
                status: ListingStatus::Sold),
            $this->entry(SellerSeeder::COLIN_EMAIL, 'Wet Plate Portrait, Nearly Headless Gentleman', 'photography', '8 x 10 in', 62000,
                'A tintype portrait made with the wet plate collodion process, each plate unique and unrepeatable. The sitter held admirably still apart from the obvious. A one-of-a-kind piece, now sold.',
                status: ListingStatus::Sold),
        ];
    }

    /**
     * @return array{seller: string, title: string, medium: string, dimensions: string, price_cents: int, description: string, status: ListingStatus, quantity: int}
     */
    private function entry(
        string $seller,
        string $title,
        string $medium,
        string $dimensions,
        int $priceCents,
        string $description,
        ListingStatus $status = ListingStatus::ForSale,
        int $quantity = 1,
    ): array {
        return [
            'seller' => $seller,
            'title' => $title,
            'medium' => $medium,
            'dimensions' => $dimensions,
            'price_cents' => $priceCents,
            'description' => $description,
            'status' => $status,
            'quantity' => $quantity,
        ];
    }
}
