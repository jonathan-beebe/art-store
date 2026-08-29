<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * A photograph for every seeded listing: real, openly licensed images
 * curated per subject (database/seeders/images — SOURCES.md carries the
 * licenses and credits), copied onto the public disk and attached as
 * {@see ListingImage} rows. Runs after every listing seeder, mapping by
 * title, so cards, category covers, and the swipe gallery all render real
 * work; a title in the map with no seeded listing fails loudly rather
 * than drifting.
 */
class ListingImageSeeder extends Seeder
{
    /**
     * Every seeded listing's photo set, in gallery order — the first file
     * is the cover.
     *
     * @var array<string, list<string>>
     */
    private const IMAGES_BY_TITLE = [
        'Bouncing Bulb, Established' => ['bouncing-bulb-established.jpg'],
        'Burrow Kitchen Tea Bowl' => ['burrow-kitchen-tea-bowl.jpg'],
        'Butterbeer Cork Necklace' => ['butterbeer-cork-necklace.jpg'],
        'Butterbeer Pitcher, Speckled Stoneware' => ['butterbeer-pitcher-speckled-stoneware.jpg'],
        'Cast Bronze Seeing Orb' => ['cast-bronze-seeing-orb.jpg'],
        'Copper Cauldron Bowl' => ['copper-cauldron-bowl.jpg'],
        'Custom Patronus Portrait' => ['custom-patronus-portrait.jpg'],
        'Diagon Alley After Rain' => ['diagon-alley-after-rain.jpg'],
        'Dirigible Plum Earrings' => ['dirigible-plum-earrings.jpg'],
        'Divination Tower Vase, Tall' => ['divination-tower-vase-tall.jpg'],
        'Engraved House Signet Ring' => ['engraved-house-signet-ring.jpg'],
        'Flitterbloom Cutting, Rooted' => ['flitterbloom-cutting-rooted.jpg'],
        'Garden Gnome in Reclaimed Oak' => ['garden-gnome-in-reclaimed-oak.jpg'],
        'Glaze Test Tiles, Series 3' => ['glaze-test-tiles-series-3.jpg'],
        'Great Hall Brass Candlesticks, Individually Listed' => ['great-hall-brass-candlesticks-individually-listed.jpg'],
        'Great Hall Serving Bowl' => ['great-hall-serving-bowl.jpg'],
        'Gryffindor Common Room, Late Morning' => ['gryffindor-common-room-late-morning.jpg'],
        'Hogsmeade Fog, Early Shift' => ['hogsmeade-fog-early-shift.jpg'],
        'Hogwarts Express, Night Crossing' => ['hogwarts-express-night-crossing.jpg'],
        'House Scarf Throw, Scarlet and Gold' => ['house-scarf-throw-scarlet-and-gold.jpg', 'house-scarf-throw-scarlet-and-gold-2.jpg'],
        'Knitted Letter Jumper, Wall Piece' => ['knitted-letter-jumper-wall-piece.jpg'],
        'Lavender Fields from the North Tower' => ['lavender-fields-from-the-north-tower.jpg'],
        'Letterpress Yule Ball Invitations' => ['letterpress-yule-ball-invitations.jpg'],
        'Line Art Kneazle Tee' => ['line-art-kneazle-tee.jpg'],
        'Live-Edge Great Hall Dining Table' => ['live-edge-great-hall-dining-table.jpg'],
        'Mimbulus Mimbletonia, Potted' => ['mimbulus-mimbletonia-potted.jpg'],
        'Naturally Dyed Silk Scarf' => ['naturally-dyed-silk-scarf.jpg'],
        'Nine Owls' => ['nine-owls.jpg'],
        'Patchwork Shawl Runner, Ochre' => ['patchwork-shawl-runner-ochre.jpg'],
        'Platform Nine and Three-Quarters' => ['platform-nine-and-three-quarters.jpg'],
        'Portrait of a Gamekeeper' => ['portrait-of-a-gamekeeper.jpg'],
        'Puffapod Seed Collection' => ['puffapod-seed-collection.jpg'],
        'Quidditch Keeper, Charcoal Study' => ['quidditch-keeper-charcoal-study.jpg'],
        'Quidditch Pitch at Dawn, 8x10 Print' => ['quidditch-pitch-at-dawn-8x10-print.jpg'],
        'Spectrespecs' => ['spectrespecs.jpg'],
        'Standing Stones, Black Lake' => ['standing-stones-black-lake.jpg'],
        'Tasseled Shawl Sampler' => ['tasseled-shawl-sampler.jpg'],
        'Tea Leaf Study' => ['tea-leaf-study.jpg'],
        'The Burrow at Dusk' => ['the-burrow-at-dusk.jpg'],
        'The Burrow at Sunset, Fine Art Print' => ['the-burrow-at-sunset-fine-art-print.jpg'],
        'The Great Lake, Noon' => ['the-great-lake-noon.jpg'],
        'The Orchard at First Light' => ['the-orchard-at-first-light.jpg'],
        'The Quibbler, Back-Issue Bundle' => ['the-quibbler-back-issue-bundle.jpg'],
        'Three Broomsticks Stoneware Mug' => ['three-broomsticks-stoneware-mug.jpg', 'three-broomsticks-stoneware-mug-2.jpg', 'three-broomsticks-stoneware-mug-3.jpg'],
        'Welded Steel Hippogriff' => ['welded-steel-hippogriff.jpg'],
        'Wet Plate Portrait, Nearly Headless Gentleman' => ['wet-plate-portrait-nearly-headless-gentleman.jpg'],
    ];

    public function run(): void
    {
        foreach (self::IMAGES_BY_TITLE as $title => $files) {
            $listing = Listing::where('title', $title)->sole();

            if ($listing->images()->exists()) {
                continue;
            }

            foreach ($files as $position => $file) {
                $path = 'listings/'.$file;
                Storage::disk('public')->put($path, File::get(database_path('seeders/images/'.$file)));

                ListingImage::create([
                    'listing_id' => $listing->id,
                    'path' => $path,
                    'position' => $position,
                ]);
            }
        }
    }
}
