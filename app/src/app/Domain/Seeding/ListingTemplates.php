<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

/**
 * The shapes `App\Domain\Seeding\ActivityPlan` draws a new listing from when
 * it schedules a seller creating one during the period — the same six
 * legacy media and price bands `database/seeders/ListingSeeder.php` already
 * seeds `make fresh` with, so a listing a plan creates reads like one that
 * could have shipped on day one.
 */
final class ListingTemplates
{
    /**
     * title, medium, category, dimensions, price_cents, quantity,
     * description — the same shape {@see \Database\Seeders\ListingSeeder}
     * builds a `ListingDraft` from.
     *
     * @var list<array{title: string, medium: string, category: string, dimensions: string, price_cents: int, quantity: int, description: string}>
     */
    private const array TEMPLATES = [
        ['title' => 'Forbidden Forest, Blue Hour', 'medium' => 'painting', 'category' => 'Art', 'dimensions' => '20 x 30 in', 'price_cents' => 58000, 'quantity' => 1, 'description' => 'The tree line at the edge of dusk, the last light caught between the trunks. Painted from the same gate the gamekeeper\'s hut looks out from.'],
        ['title' => 'Prefects\' Bathroom, Steam and Tile', 'medium' => 'painting', 'category' => 'Art', 'dimensions' => '18 x 24 in', 'price_cents' => 49000, 'quantity' => 1, 'description' => 'Rising steam softens every tap and tile into a single warm haze. A study in reflected light rather than any one fixture.'],
        ['title' => 'Quidditch Pitch, Empty Stands', 'medium' => 'painting', 'category' => 'Art', 'dimensions' => '24 x 30 in', 'price_cents' => 62000, 'quantity' => 1, 'description' => 'The pitch the morning after a match, hoops still standing, stands bare. Grass worked in short, dragged strokes.'],
        ['title' => 'Owlery at First Light', 'medium' => 'print', 'category' => 'Art', 'dimensions' => '14 x 18 in', 'price_cents' => 9500, 'quantity' => 3, 'description' => 'A screenprint of the owlery rafters, silhouettes only. Edition of twenty, hand-numbered.'],
        ['title' => 'Honeydukes Window, Winter', 'medium' => 'print', 'category' => 'Art', 'dimensions' => '12 x 16 in', 'price_cents' => 7000, 'quantity' => 4, 'description' => 'The shop window under snow, jars and boxes reduced to flat blocks of color. Risograph, two passes.'],
        ['title' => 'Whomping Willow, Mid-Swing', 'medium' => 'photography', 'category' => 'Art', 'dimensions' => '16 x 20 in', 'price_cents' => 41000, 'quantity' => 1, 'description' => 'A long exposure that turns branches into a blur of motion against a still sky. Printed archival, on cotton rag.'],
        ['title' => 'Astronomy Tower, Night Sky', 'medium' => 'photography', 'category' => 'Art', 'dimensions' => '20 x 30 in', 'price_cents' => 55000, 'quantity' => 1, 'description' => 'The tower against a clear night, stars trailed by a long shutter. Shot on medium-format film.'],
        ['title' => 'Hufflepuff Kitchen Mug', 'medium' => 'ceramic', 'category' => 'Home Goods', 'dimensions' => '4 x 4 x 4 in', 'price_cents' => 6500, 'quantity' => 3, 'description' => 'A stoneware mug glazed in warm ochre, a badger pressed into the base before firing.'],
        ['title' => 'Prefect\'s Bathroom Soap Dish', 'medium' => 'ceramic', 'category' => 'Home Goods', 'dimensions' => '6 x 4 x 2 in', 'price_cents' => 4200, 'quantity' => 5, 'description' => 'A slab-built dish with a raised lip, glazed in a break-away blue that pools at the edges.'],
        ['title' => 'Slytherin Dungeon Vase', 'medium' => 'ceramic', 'category' => 'Home Goods', 'dimensions' => '10 x 5 x 5 in', 'price_cents' => 21000, 'quantity' => 2, 'description' => 'A thrown vase in a deep green celadon, fired to pool darker where the glaze runs thick.'],
        ['title' => 'Ravenclaw Common Room Throw', 'medium' => 'textile', 'category' => 'Home Goods', 'dimensions' => '50 x 70 in', 'price_cents' => 29500, 'quantity' => 2, 'description' => 'A plain-weave throw in blue and bronze, woven on a floor loom over a fortnight.'],
        ['title' => 'Marauder\'s Map Table Runner', 'medium' => 'textile', 'category' => 'Home Goods', 'dimensions' => '16 x 72 in', 'price_cents' => 16500, 'quantity' => 3, 'description' => 'A printed linen runner tracing a hand-drawn corridor pattern, hemmed on all four sides.'],
        ['title' => 'Basilisk Fang Letter Opener', 'medium' => 'sculpture', 'category' => 'Home Goods', 'dimensions' => '9 x 1 x 1 in', 'price_cents' => 38000, 'quantity' => 1, 'description' => 'Cast bronze, curved and polished to a dull shine, mounted on a small oak stand.'],
        ['title' => 'Welded Steel Thestral', 'medium' => 'sculpture', 'category' => 'Home Goods', 'dimensions' => '18 x 12 x 22 in', 'price_cents' => 112000, 'quantity' => 1, 'description' => 'Rod and sheet steel bent into a skeletal, wide-winged form, left to weather outdoors.'],
        ['title' => 'Herbology Greenhouse Cutting', 'medium' => 'plant', 'category' => 'Home Goods', 'dimensions' => '9 in pot', 'price_cents' => 3800, 'quantity' => 4, 'description' => 'A rooted cutting from the greenhouse benches, easy-going and quick to establish in a bright window.'],
        ['title' => 'Devil\'s Snare Look-Alike, Potted', 'medium' => 'plant', 'category' => 'Home Goods', 'dimensions' => '12 x 8 x 8 in', 'price_cents' => 5200, 'quantity' => 3, 'description' => 'A trailing houseplant bred to resemble the real thing without any of the temperament.'],
        ['title' => 'Chocolate Frog Card Locket', 'medium' => 'jewelry', 'category' => 'Jewelry', 'dimensions' => '1.5 in pendant', 'price_cents' => 4400, 'quantity' => 6, 'description' => 'A small hinged locket sized for one card, on a fine brass chain.'],
        ['title' => 'Butterbeer Cap Cufflinks', 'medium' => 'jewelry', 'category' => 'Jewelry', 'dimensions' => '0.75 in each', 'price_cents' => 3200, 'quantity' => 5, 'description' => 'A pair of cufflinks cast from a real bottle cap, backed in brushed silver.'],
        ['title' => 'The Quibbler, Reader Bundle', 'medium' => 'publication', 'category' => 'Stationery', 'dimensions' => '8.5 x 11 in, set of 4', 'price_cents' => 1100, 'quantity' => 8, 'description' => 'Four assorted issues, no two bundles quite alike.'],
        ['title' => 'Sorting Hat Desk Charm', 'medium' => 'curio', 'category' => 'Home Goods', 'dimensions' => '4 x 3 x 3 in', 'price_cents' => 2600, 'quantity' => 6, 'description' => 'A miniature felt hat on a weighted base, made to sit on a shelf rather than a head.'],
    ];

    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return list<array{title: string, medium: string, category: string, dimensions: string, price_cents: int, quantity: int, description: string}>
     */
    public static function all(): array
    {
        return self::TEMPLATES;
    }
}
