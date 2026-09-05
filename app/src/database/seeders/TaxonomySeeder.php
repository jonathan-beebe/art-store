<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Configurator\PropertyDataType;
use App\Models\Category;
use App\Models\CategoryProperty;
use App\Models\Property;
use App\Models\PropertyValue;
use Illuminate\Database\Seeder;

/**
 * A small believable category tree — enough to host the eight configurator
 * archetypes — with the properties each category grants and how
 * (attribute, axis, required). Reference data: written directly, the way
 * the rest of the app's config-only rows are (docs/architecture.md's
 * model-layer writes).
 */
class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        if (Category::query()->exists()) {
            return;
        }

        $jewelry = Category::create(['name' => 'Jewelry', 'path' => '/jewelry/']);
        $rings = Category::create(['parent_id' => $jewelry->id, 'name' => 'Rings', 'path' => '/jewelry/rings/']);
        $homeGoods = Category::create(['name' => 'Home Goods', 'path' => '/home-goods/']);
        $furniture = Category::create(['parent_id' => $homeGoods->id, 'name' => 'Furniture', 'path' => '/home-goods/furniture/']);
        $apparel = Category::create(['name' => 'Apparel', 'path' => '/apparel/']);
        $art = Category::create(['name' => 'Art', 'path' => '/art/']);
        $stationery = Category::create(['name' => 'Stationery', 'path' => '/stationery/']);

        $metal = $this->property('Metal', ['Gold', 'Silver', 'Rose Gold']);
        $this->grant($jewelry, $metal, usableAsAttribute: true, usableAsAxis: true);

        // A specific-type property (§2.1 "Attribute altitude"): no buyer
        // choice states it as an attribute (the garden gnome's fixed
        // reclaimed oak), a buyer choice builds an axis referencing it (the
        // walnut table's Wood axis) — same grant, both flags meaningful,
        // matching Metal above.
        $woodSpecies = $this->property('Wood Species', ['Walnut', 'Oak', 'Maple']);
        $this->grant($furniture, $woodSpecies, usableAsAttribute: true, usableAsAxis: true);

        $ringSize = $this->property('Ring Size', ['6', '7', '8', '9']);
        $this->grant($rings, $ringSize, usableAsAttribute: false, usableAsAxis: true, required: true);

        $color = $this->property('Color', ['Black', 'White', 'Heather Grey']);
        $this->grant($apparel, $color, usableAsAttribute: true, usableAsAxis: true);

        $size = $this->property('Size', ['S', 'M', 'L', 'XL', 'XXL']);
        $this->grant($apparel, $size, usableAsAttribute: false, usableAsAxis: true, required: true);

        // One high-level vocabulary (FEAT-031): each value is a browse-altitude
        // fact ("this is wood", "this is a sculpture") that covers every
        // legacy `medium` string the seeders carry. A specific type — which
        // wood, which metal — lives on an option axis instead (the walnut
        // table's Wood axis, the ring's Metal axis), never a second attribute
        // vocabulary.
        $medium = $this->property('Medium', [
            'Painting', 'Print', 'Photograph', 'Ceramic', 'Textile', 'Sculpture',
            'Wood', 'Metal', 'Paper', 'Plant', 'Publication', 'Curio', 'Jewelry', 'Apparel',
        ]);
        $this->grant($art, $medium, usableAsAttribute: true, usableAsAxis: false, required: true);
        $this->grant($jewelry, $medium, usableAsAttribute: true, usableAsAxis: false);
        $this->grant($rings, $medium, usableAsAttribute: true, usableAsAxis: false);
        // Home Goods' grant is multivalued: a sculpture built from a
        // reclaimed beam is genuinely both Sculpture and Wood at once, where
        // Furniture's grant on the same property stays single-valued — the
        // walnut table's specific wood lives on its own axis instead.
        $this->grant($homeGoods, $medium, usableAsAttribute: true, usableAsAxis: false, multivalued: true);
        // Required (FEAT-032): Furniture also grants Wood Species, so the
        // "species implies wood" curation rule holds — a table can never
        // state Walnut without also stating Wood.
        $this->grant($furniture, $medium, usableAsAttribute: true, usableAsAxis: false, required: true);
        $this->grant($apparel, $medium, usableAsAttribute: true, usableAsAxis: false);
        $this->grant($stationery, $medium, usableAsAttribute: true, usableAsAxis: false);

        $paperStock = $this->property('Paper Stock', ['Standard', 'Pearl Shimmer', 'Cotton Linen']);
        $this->grant($stationery, $paperStock, usableAsAttribute: false, usableAsAxis: true);
    }

    /**
     * @param  list<string>  $values
     */
    private function property(string $name, array $values): Property
    {
        $property = Property::create(['name' => $name, 'data_type' => PropertyDataType::Enum]);

        foreach ($values as $position => $label) {
            PropertyValue::create(['property_id' => $property->id, 'label' => $label, 'position' => $position]);
        }

        return $property;
    }

    private function grant(
        Category $category,
        Property $property,
        bool $usableAsAttribute,
        bool $usableAsAxis,
        bool $required = false,
        bool $multivalued = false,
    ): void {
        CategoryProperty::create([
            'category_id' => $category->id,
            'property_id' => $property->id,
            'usable_as_attribute' => $usableAsAttribute,
            'usable_as_axis' => $usableAsAxis,
            'required' => $required,
            'multivalued' => $multivalued,
        ]);
    }
}
