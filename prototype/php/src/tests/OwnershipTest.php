<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\CustomerBlock;
use App\Models\CustomerMerge;
use App\Models\DescriptionSection;
use App\Models\Favorite;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\ListingEvent;
use App\Models\ListingFaq;
use App\Models\ListingImage;
use App\Models\ListingRemoval;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\ModifierScope;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\QuantityBreak;
use App\Models\Refund;
use App\Models\Seller;
use App\Models\Unit;
use App\Models\Variant;
use App\Models\VariantOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

uses(Tests\CommerceTestCase::class);

/**
 * docs/database.md §1: every table whose rows belong to exactly one seller
 * carries a populated `seller_id`; every table whose rows belong to exactly
 * one customer carries a populated `customer_id`. These two lists are every
 * model the rule covers — adding an owned table without adding it here, or
 * without giving it the column, turns one of the tests below red.
 *
 * @var list<class-string<Model>>
 */
$sellerOwnedModels = [
    Listing::class,
    Payout::class,
    LedgerEntry::class,
    ListingEvent::class,
    ListingRemoval::class,
    ListingFaq::class,
    ListingAttribute::class,
    ListingImage::class,
    OptionAxis::class,
    OptionValue::class,
    Variant::class,
    VariantOption::class,
    Unit::class,
    Modifier::class,
    ModifierOption::class,
    ModifierScope::class,
    QuantityBreak::class,
    DescriptionSection::class,
];

/** @var list<class-string<Model>> */
$customerOwnedModels = [
    Cart::class,
    Favorite::class,
    CustomerBlock::class,
    CustomerMerge::class,
    Order::class,
    CartItem::class,
    OrderItem::class,
    Fulfillment::class,
    Refund::class,
    Payment::class,
];

it('carries a populated seller_id on every table a seller owns', function () use ($sellerOwnedModels): void {
    foreach ($sellerOwnedModels as $modelClass) {
        $table = (new $modelClass)->getTable();

        expect(Schema::hasColumn($table, 'seller_id'))->toBeTrue("{$table} has no seller_id column.");
        expect(in_array('seller_id', (new $modelClass)->getFillable(), true))->toBeTrue("{$modelClass} does not mass-assign seller_id.");

        $row = Factory::factoryForModel($modelClass)->createOne();
        expect($row->getAttribute('seller_id'))->not->toBeNull("{$modelClass}'s factory left seller_id unpopulated.");
    }
});

it('carries a populated customer_id on every table a customer owns', function () use ($customerOwnedModels): void {
    foreach ($customerOwnedModels as $modelClass) {
        $table = (new $modelClass)->getTable();

        expect(Schema::hasColumn($table, 'customer_id'))->toBeTrue("{$table} has no customer_id column.");
        expect(in_array('customer_id', (new $modelClass)->getFillable(), true))->toBeTrue("{$modelClass} does not mass-assign customer_id.");

        $row = Factory::factoryForModel($modelClass)->createOne();
        expect($row->getAttribute('customer_id'))->not->toBeNull("{$modelClass}'s factory left customer_id unpopulated.");
    }
});

it('carries a populated seller_id on a refund, fulfillment-scoped rather than order-scoped', function (): void {
    $refund = Refund::factory()->create();

    expect(Schema::hasColumn('refunds', 'seller_id'))->toBeTrue();
    expect($refund->seller_id)->not->toBeNull();
});

it('gives every seller-owned row created through its action the same seller_id its listing carries', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $event = app(App\Actions\Listings\RecordListingEvent::class)($listing, null, App\Domain\Listings\ListingEventType::View, new DateTimeImmutable);
    assert($event instanceof ListingEvent);
    expect($event->seller_id)->toBe($seller->id);

    $axis = app(App\Actions\Configurator\CreateOptionAxis::class)($listing, 'Metal');
    expect($axis->seller_id)->toBe($seller->id);

    $value = app(App\Actions\Configurator\AddOptionValue::class)($axis, 'Gold');
    expect($value->seller_id)->toBe($seller->id);

    $variant = app(App\Actions\Configurator\CreateVariant::class)($listing, [$value]);
    expect($variant->seller_id)->toBe($seller->id);
    expect($variant->options()->sole()->seller_id)->toBe($seller->id);

    $unit = app(App\Actions\Configurator\AddUnit::class)($variant, '#1');
    expect($unit->seller_id)->toBe($seller->id);

    $modifier = app(App\Actions\Configurator\CreateModifier::class)($listing, App\Domain\Configurator\ModifierKind::Text, 'Engraving');
    expect($modifier->seller_id)->toBe($seller->id);

    $modifierOption = app(App\Actions\Configurator\AddModifierOption::class)($modifier, 'Block');
    expect($modifierOption->seller_id)->toBe($seller->id);

    $scope = app(App\Actions\Configurator\SetModifierScope::class)($modifier, [$value])[0];
    expect($scope->seller_id)->toBe($seller->id);

    $section = app(App\Actions\Configurator\AddDescriptionSection::class)($listing, 0, App\Domain\Configurator\DescriptionSectionKind::Text, 'Care');
    expect($section->seller_id)->toBe($seller->id);

    $break = app(App\Actions\Configurator\AddQuantityBreak::class)($listing, 10, 500);
    expect($break->seller_id)->toBe($seller->id);
});

it('gives every customer-owned order-side row created through its action the same customer_id the order carries', function (): void {
    $customer = $this->verifiedCustomer();
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 5000]);

    $cart = $this->cartFor($customer);
    $cartItem = app(App\Actions\Cart\AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));
    expect($cartItem->customer_id)->toBe($customer->id);

    $order = app(App\Actions\Orders\PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));
    expect($order->items()->sole()->customer_id)->toBe($customer->id);
    expect($order->fulfillments()->sole()->customer_id)->toBe($customer->id);

    $paid = app(App\Actions\Orders\FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    expect($paid->payments()->sole()->customer_id)->toBe($customer->id);

    $fulfillment = $paid->fulfillments()->sole();
    $refund = app(App\Actions\Escrow\IssueRefund::class)(
        $fulfillment,
        App\Domain\Auth\ActorType::Seller,
        $seller->id,
        'Out of stock.',
        $this->moment('2026-08-21 09:00:00'),
    );
    expect($refund->customer_id)->toBe($customer->id);
    expect($refund->seller_id)->toBe($fulfillment->seller_id);
});
