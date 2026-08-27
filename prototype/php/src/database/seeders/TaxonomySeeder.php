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
 * (attribute, axis, required). Reference data: written directly rather than
 * through an action, the way the rest of this prototype's config-only rows
 * are (docs/architecture.md's model-layer writes).
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

        $ringSize = $this->property('Ring Size', ['6', '7', '8', '9']);
        $this->grant($rings, $ringSize, usableAsAttribute: false, usableAsAxis: true, required: true);

        $material = $this->property('Material', ['Walnut', 'Brass', 'Oak', 'Cotton']);
        $this->grant($homeGoods, $material, usableAsAttribute: true, usableAsAxis: false);
        // Furniture's own grant is multivalued: a table can be Walnut and Oak
        // at once, where Home Goods' grant on the same property stays
        // single-valued for its other listings.
        $this->grant($furniture, $material, usableAsAttribute: true, usableAsAxis: false, multivalued: true);

        $color = $this->property('Color', ['Black', 'White', 'Heather Grey']);
        $this->grant($apparel, $color, usableAsAttribute: true, usableAsAxis: true);

        $size = $this->property('Size', ['S', 'M', 'L', 'XL', 'XXL']);
        $this->grant($apparel, $size, usableAsAttribute: false, usableAsAxis: true, required: true);

        // The storefront's whole media vocabulary (FEAT-030): the four
        // original values (Print, Watercolor, Photograph kept as-is; Oil kept
        // even though no seeded listing uses it today) plus every legacy
        // `medium` string the seeders carry, so `listing_attributes` can
        // answer the storefront filter for anything the store sells.
        $medium = $this->property('Medium', [
            'Print', 'Oil', 'Watercolor', 'Photograph',
            'Painting', 'Ceramic', 'Textile', 'Sculpture', 'Plant', 'Publication',
            'Curio', 'Jewelry', 'Metal', 'Apparel', 'Walnut', 'Brass', 'Paper',
        ]);
        $this->grant($art, $medium, usableAsAttribute: true, usableAsAxis: false, required: true);
        $this->grant($jewelry, $medium, usableAsAttribute: true, usableAsAxis: false);
        $this->grant($rings, $medium, usableAsAttribute: true, usableAsAxis: false);
        $this->grant($homeGoods, $medium, usableAsAttribute: true, usableAsAxis: false);
        $this->grant($furniture, $medium, usableAsAttribute: true, usableAsAxis: false);
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
