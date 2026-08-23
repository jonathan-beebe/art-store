<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Listings\ListingStatus;
use App\Models\Listing;
use App\Models\Seller;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 24 for_sale listings across six media, three drafts, and two sold-out
 * pieces. Three of the for_sale listings start at quantity 2 so
 * OrderHistorySeeder can sell one unit of each and leave them on the
 * storefront.
 */
class ListingSeeder extends Seeder
{
    public function run(): void
    {
        $sellerIds = Seller::query()->pluck('id', 'email');

        foreach ($this->listings() as $listing) {
            Listing::create([
                'seller_id' => $sellerIds[$listing['seller']],
                'title' => $listing['title'],
                'slug' => Str::slug($listing['title']),
                'description' => $listing['description'],
                'price_cents' => $listing['price_cents'],
                'quantity' => $listing['quantity'],
                'status' => $listing['status'],
                'medium' => $listing['medium'],
                'dimensions' => $listing['dimensions'],
            ]);
        }
    }

    /**
     * @return list<array{seller: string, title: string, medium: string, dimensions: string, price_cents: int, description: string, status: ListingStatus, quantity: int}>
     */
    private function listings(): array
    {
        return [
            $this->entry(SellerSeeder::MAYA_EMAIL, 'Low Tide at Dusk', 'painting', '24 x 36 in', 68000,
                'A wide horizon in muted blue and rust orange as the tide pulls back over wet sand. Palette-knife strokes build texture into the foreground rocks. Painted en plein air over three sessions on the Oregon coast.'),
            $this->entry(SellerSeeder::NOAH_EMAIL, 'Kitchen Table, Late Morning', 'painting', '18 x 24 in', 42000,
                'Light crosses a cluttered kitchen table, catching the rim of a coffee cup and a half-folded newspaper. Loose brushwork keeps the scene from feeling staged. Part of an ongoing series on domestic quiet.',
                quantity: 2),
            $this->entry(SellerSeeder::PRIYA_EMAIL, 'Field Study No. 12', 'painting', '30 x 40 in', 95000,
                'Rows of lavender recede toward a treeline under a bruised summer sky. Thin glazes sit over a toned ground, so the underpainting shows through the purple. Twelfth canvas in a series painted across one growing season.'),
            $this->entry(SellerSeeder::LEO_EMAIL, 'Harbor Fog, Early Shift', 'painting', '20 x 30 in', 76000,
                'Trawlers sit at anchor behind a scrim of morning fog, hulls barely distinct from the water. A single sodium lamp on the dock anchors the composition. Reference photos came from a week spent on a working harbor.'),

            $this->entry(SellerSeeder::MAYA_EMAIL, 'Nine Herons', 'print', '16 x 20 in', 12000,
                'Nine herons in profile, carved in a single block and printed in three passes of grey ink. Each bird holds a different angle of the neck, drawn from a winter spent at a tidal marsh. Edition of thirty, hand-numbered.'),
            $this->entry(SellerSeeder::NOAH_EMAIL, 'Terminal, Platform 4', 'print', '18 x 24 in', 15000,
                'A commuter platform rendered in four flat colors, the crowd reduced to silhouettes and one lit sign. Screenprinted by hand in small batches. Part of a set of transit prints made from station sketches.'),
            $this->entry(SellerSeeder::PRIYA_EMAIL, 'Marigold Study', 'print', '11 x 14 in', 6000,
                'A single marigold stem printed in two risograph passes, orange over a warm grey. The registration sits slightly loose on purpose, so the layers separate at the edges. Riso printing keeps each run different from the last.'),
            $this->entry(SellerSeeder::LEO_EMAIL, 'Night Freight', 'print', '14 x 18 in', 22000,
                'A freight train crosses a trestle bridge at night, the headlamp the only bright point on the plate. Deep bitten lines carry the dark, aquatint fills the sky. Printed on a hand press in an edition of twelve.'),

            $this->entry(SellerSeeder::MAYA_EMAIL, 'Ash-Glazed Tea Bowl', 'ceramic', '4 x 4 x 3 in', 8500,
                'A stoneware tea bowl fired with wood ash landing across the shoulder in a natural drip. The foot is trimmed thin and left unglazed to show the clay body. Fired in a three-day anagama firing.',
                quantity: 2),
            $this->entry(SellerSeeder::NOAH_EMAIL, 'Speckled Stoneware Pitcher', 'ceramic', '9 x 6 x 6 in', 14000,
                'A pitcher thrown in a speckled stoneware clay, pulled handle attached while the body is still soft. The spout is cut for a clean pour rather than a decorative flare. Glazed in a satin oatmeal that breaks over the throwing rings.'),
            $this->entry(SellerSeeder::PRIYA_EMAIL, 'Woodfired Vase, Tall', 'ceramic', '14 x 6 x 6 in', 32000,
                'A tall thrown vase, fired unglazed in a wood kiln so ash and flame draw a map of color across the surface. No two sides read the same. Fourteen inches gives it enough height for a single branch or a full arrangement.'),
            $this->entry(SellerSeeder::LEO_EMAIL, 'Salt-Glazed Serving Bowl', 'ceramic', '12 x 12 x 4 in', 19500,
                'A wide serving bowl salt-glazed to an orange-peel texture, the rim left slightly irregular from the wheel. Food-safe and built for daily use rather than display. Fires to a warm amber wherever the flame reaches it directly.'),

            $this->entry(SellerSeeder::MAYA_EMAIL, 'Indigo Shibori Wall Hanging', 'textile', '36 x 48 in', 24000,
                'A cotton panel bound and dyed in indigo using a folded arashi technique, the pattern reading as a field of diagonal rain. Dyed in four successive baths to build depth. Hung from a raw dowel with visible stitching.'),
            $this->entry(SellerSeeder::NOAH_EMAIL, 'Handwoven Mohair Throw', 'textile', '50 x 70 in', 32000,
                'A plain-weave throw in undyed mohair and a fine wool warp, woven on a floor loom over two weeks. The natural fiber colors run cream through charcoal without any dye. Fringe is hand-twisted at both ends.'),
            $this->entry(SellerSeeder::PRIYA_EMAIL, 'Rag-Rug Runner, Ochre', 'textile', '24 x 72 in', 18000,
                'A rag rug woven from strips of reclaimed cotton fabric in a range of ochre and rust. Each strip carries a trace of its previous life as clothing or bedding. Reversible, with a matching pattern on both sides.'),
            $this->entry(SellerSeeder::LEO_EMAIL, 'Naturally Dyed Silk Scarf', 'textile', '18 x 72 in', 9500,
                "A silk habotai scarf dyed with onion skin and marigold, giving a gradient from pale gold to deep amber. Hand-hemmed along all four edges. Each dye lot varies with the season's plant material."),

            $this->entry(SellerSeeder::MAYA_EMAIL, 'Standing Figure in Reclaimed Oak', 'sculpture', '22 x 8 x 8 in', 185000,
                'A standing figure carved from a single piece of reclaimed oak beam, the surface left with visible chisel marks. The grain of the old beam runs through the torso like a seam. Finished with hand-rubbed oil rather than a film coating.',
                quantity: 2),
            $this->entry(SellerSeeder::NOAH_EMAIL, 'Welded Steel Corvid', 'sculpture', '16 x 10 x 20 in', 96000,
                'A crow built from welded steel plate and rod, the feathers suggested with cut sheet rather than modeled in detail. The finish is a raw steel patina, left to develop rust over time. Stands free on a flat steel base.'),
            $this->entry(SellerSeeder::PRIYA_EMAIL, 'Cast Bronze Seed Pod', 'sculpture', '10 x 6 x 6 in', 145000,
                'A seed pod form cast in bronze from a wax original, patinated to a deep green over brown. The surface holds the fine texture of the original carving. Cast in a lost-wax edition of eight.'),
            $this->entry(SellerSeeder::LEO_EMAIL, 'Balanced Stone Cairn', 'sculpture', '30 x 12 x 12 in', 68000,
                'Four fieldstones stacked and pinned along a hidden steel rod, the balance point of each stone left visible. Stone comes from a single riverbed, chosen for color and grain across the set. Built for an outdoor garden setting.'),

            $this->entry(SellerSeeder::MAYA_EMAIL, 'Quarry at First Light', 'photography', '24 x 36 in', 45000,
                'An abandoned quarry photographed at first light, mist still sitting in the lowest cut. Printed as an archival pigment print on cotton rag paper. Shot on medium-format film and scanned at high resolution.'),
            $this->entry(SellerSeeder::NOAH_EMAIL, 'Neon After Rain', 'photography', '20 x 30 in', 38000,
                'A city street after rain, neon signs doubled in the wet pavement. A long exposure holds the blur of a single passing car. Printed in a limited run of fifteen.'),
            $this->entry(SellerSeeder::PRIYA_EMAIL, 'Salt Flats, Noon', 'photography', '30 x 40 in', 52000,
                "A salt flat under a noon sun, the horizon line barely visible between white ground and white sky. A lone figure stands near the frame's edge for scale. Printed large to hold the flatness of the light."),
            $this->entry(SellerSeeder::LEO_EMAIL, 'Portrait of a Welder', 'photography', '16 x 20 in', 29500,
                'A welder mid-task, arc light catching the edge of the mask and glove. Shot on black-and-white film and printed in a wet darkroom. Part of a portrait series on trade work.'),

            $this->entry(SellerSeeder::NOAH_EMAIL, 'Untitled Charcoal Study', 'painting', '18 x 24 in', 15000,
                'A charcoal figure study from a single studio session, kept loose and unfinished. Working drawing for a larger painting still in progress.',
                status: ListingStatus::Draft),
            $this->entry(SellerSeeder::PRIYA_EMAIL, 'Waxed Linen Sampler', 'textile', '20 x 20 in', 12000,
                'A test panel of waxed linen dyed in three tannin baths, made to check color before a full-size piece. Not yet mounted or finished at the edges.',
                status: ListingStatus::Draft),
            $this->entry(SellerSeeder::LEO_EMAIL, 'Kiln Test Tiles, Series 3', 'ceramic', '6 x 6 in each', 4000,
                'A set of glaze test tiles from the third round of a new ash glaze recipe. Kept as a reference rather than sold, listed here as a draft.',
                status: ListingStatus::Draft),

            $this->entry(SellerSeeder::MAYA_EMAIL, 'Copper Patina Bowl', 'ceramic', '10 x 10 x 4 in', 22000,
                'A thrown bowl finished with a copper-oxide wash that fires to a mottled green and black. The last piece from a small batch fired in the spring.',
                status: ListingStatus::Sold, quantity: 0),
            $this->entry(SellerSeeder::LEO_EMAIL, 'Wet Plate Collodion Portrait', 'photography', '8 x 10 in', 62000,
                'A tintype portrait made with the wet plate collodion process, each plate unique and unrepeatable. A one-of-a-kind piece, now sold.',
                status: ListingStatus::Sold, quantity: 0),
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
