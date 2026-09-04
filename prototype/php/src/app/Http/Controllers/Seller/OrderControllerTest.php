<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Cart\AddToCart;
use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateModifier;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\GenerateVariants;
use App\Actions\Fulfillment\CompleteFlowStep;
use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\DeclineFulfillment;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\PlaceOrder;
use App\Domain\Auth\ActorType;
use App\Domain\Configurator\ModifierKind;
use App\Domain\Messaging\ConversationKind;
use App\Models\Conversation;
use App\Models\Fulfillment;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;
use App\Models\Message;
use App\Models\Seller;
use App\Models\Variant;

$paidFulfillment = function (Seller $seller, string $title = 'Harbour at Dusk'): Fulfillment {
    $order = test()->orderFor(test()->verifiedCustomer(), test()->listing($seller, ['title' => $title]));
    app(FinalizeOrder::class)($order, '4242424242424242', test()->moment('2026-08-20 10:00:00'));

    return Fulfillment::where('seller_id', $seller->id)->sole();
};

it('lists the fulfillment as a list-pane row with its status badge', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $paidFulfillment($seller, 'Harbour at Dusk');

    $response = $this->actingAs($seller, 'seller')->get('/seller/orders');

    $response->assertOk();
    $response->assertSee('Harbour at Dusk');
    $response->assertSee('Awaiting shipment');
});

it('opens on the pile that asks for work', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $paidFulfillment($seller, 'Harbour at Dusk');
    $this->shippedFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get('/seller/orders');

    $response->assertOk();
    $response->assertSee('Harbour at Dusk');
    $response->assertSee('To ship');
    $response->assertSee('In progress');
});

it('shows an empty-detail prompt until an order is selected', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/orders');

    $response->assertOk();
    $response->assertSee('Choose an order to see its details.');
});

it('marks the open fulfillment selected in the list pane', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertOk();
    $response->assertSee('aria-current="page"', escape: false);
});

it('keeps another sellers fulfillments off the page', function () use ($paidFulfillment): void {
    $paidFulfillment($this->seller('Other Studio'), 'Not Mine');

    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/orders');

    $response->assertDontSee('Not Mine');
});

it('shows the shipping address and the sellers items', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller, 'Harbour at Dusk');

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertOk();
    $response->assertSee('Ada Lovelace');
    $response->assertSee('12 Analytical Way');
    $response->assertSee('Harbour at Dusk');
});

it('leaves another sellers items off the order', function (): void {
    $seller = $this->seller();
    $other = $this->seller('Other Studio');
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor(
        $customer,
        $this->listing($seller, ['title' => 'Mine']),
        $this->listing($other, ['title' => 'Theirs']),
    );
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee('Mine');
    $response->assertDontSee('Theirs');
});

it('offers the mark shipped form while a fulfillment awaits shipment', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee('for="carrier"', escape: false);
    $response->assertSee('for="tracking_number"', escape: false);
});

it('shows the shipment details once shipped', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);
    app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM123', $this->moment('2026-08-21 10:00:00'));

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee('Royal Mail');
    $response->assertSee('RM123');
    $response->assertSee('Aug 21, 2026');
    $response->assertDontSee('for="carrier"', escape: false);
});

it('shows the delivered timestamp', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);
    app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM123', $this->moment('2026-08-21 10:00:00'));
    app(ConfirmDelivered::class)($fulfillment->refresh(), $this->moment('2026-08-23 09:00:00'));

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee('Aug 23, 2026');
});

it('hides another sellers fulfillment', function () use ($paidFulfillment): void {
    $fulfillment = $paidFulfillment($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertNotFound();
});

it('renders a configured lines configuration and itemized breakdown', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Engraved Signet Ring', 'price_cents' => 12000]);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    $roseGold = app(AddOptionValue::class)($metal, 'Rose Gold', 800);
    app(GenerateVariants::class)($listing);
    $variant = Variant::whereHas('options', fn ($query) => $query->where('option_value_id', $roseGold->id))->sole();

    $customer = $this->verifiedCustomer();
    $cart = $this->cartFor($customer);
    app(AddToCart::class)(
        $cart,
        $listing,
        1,
        $this->moment('2026-08-20 08:00:00'),
        listingHasVariants: true,
        variant: $variant,
        configuration: [['axisId' => $metal->id, 'axisName' => 'Metal', 'optionValueId' => $roseGold->id, 'optionValueLabel' => 'Rose Gold']],
    );
    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertOk();
    $response->assertSee('Metal:');
    $response->assertSee('Rose Gold');
    $response->assertSee('Base price');
    $response->assertSee('$128.00');
});

it('B9: shows an answered question on the order the seller fulfills', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Hand-Lettered Name Mug', 'price_cents' => 1400]);
    $size = app(CreateOptionAxis::class)($listing, 'Size');
    $eightOz = app(AddOptionValue::class)($size, '8 oz', 0, isDefault: true);
    app(GenerateVariants::class)($listing);
    $variant = Variant::whereHas('options', fn ($query) => $query->where('option_value_id', $eightOz->id))->sole();
    $modifier = app(CreateModifier::class)($listing, ModifierKind::Text, 'Name to letter', addOnPriceCents: 200);

    $customer = $this->verifiedCustomer();
    $cart = $this->cartFor($customer);
    app(AddToCart::class)(
        $cart,
        $listing,
        1,
        $this->moment('2026-08-20 08:00:00'),
        listingHasVariants: true,
        variant: $variant,
        configuration: [['axisId' => $size->id, 'axisName' => 'Size', 'optionValueId' => $eightOz->id, 'optionValueLabel' => '8 oz']],
        answers: [$modifier->id => ['prompt' => 'Name to letter', 'answer' => 'Wren', 'raw' => 'Wren']],
        fingerprintAnswers: [$modifier->id => 'Wren'],
    );
    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertOk();
    $response->assertSee('Name to letter:');
    $response->assertSee('Wren');
});

it('answers not found for a value that is not a fulfillment id, the same as an unknown one', function (string $id): void {
    $this->actingAs($this->seller(), 'seller')->get("/seller/orders/{$id}")->assertNotFound();
})->with([
    'another table prefix' => 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a bare ULID' => '01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a value of no shape at all' => 'nonsense',
    'a fulfillment that does not exist' => 'ful_01J5X3M9A2K8YB7Q4R6T1V0WZE',
]);

it('offers the decline form on a parcel that has not shipped', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertOk();
    $response->assertSee('puts your pieces back on the storefront');
});

it('withdraws the decline form once the parcel shipped', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->shippedFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertOk();
    $response->assertDontSee('puts your pieces back on the storefront');
});

it('shows the refund behind a declined parcel', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);
    app(DeclineFulfillment::class)($fulfillment, 'The kiln cracked the glaze.', $this->moment('2026-08-21 09:00:00'));

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertOk();
    $response->assertSee('The kiln cracked the glaze.');
    $response->assertSee('Seller');
});

it('keeps each lane to the parcels that belong in it', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $paidFulfillment($seller, 'Waiting To Go');
    $this->shippedFulfillmentFor($seller);
    $this->deliveredFulfillmentFor($seller);

    $toShip = $this->actingAs($seller, 'seller')->get('/seller/orders?lane=ship');
    $done = $this->actingAs($seller, 'seller')->get('/seller/orders?lane=done');

    $toShip->assertSee('Waiting To Go');
    $toShip->assertDontSee('Delivered');
    $done->assertSee('Delivered');
    $done->assertDontSee('Waiting To Go');
});

it('shows every parcel under the All lane', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $paidFulfillment($seller, 'Waiting To Go');
    $this->deliveredFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get('/seller/orders?lane=all');

    $response->assertSee('Waiting To Go');
    $response->assertSee('Delivered');
});

it('counts what the two working lanes hold on their own tabs', function (): void {
    $seller = $this->seller('Molly Weasley');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $step = FulfillmentFlowStep::factory()->of($flow, 0)->create(['label' => 'Packed', 'key' => 'packed']);
    $started = $this->paidFulfillmentFor($seller);
    app(CompleteFlowStep::class)($started, $step, null, null, $this->moment('2026-08-21 09:00:00'));
    $this->paidFulfillmentFor($seller);
    $this->paidFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get('/seller/orders');

    $response->assertSeeInOrder(['To ship', '2', 'In progress', '1']);
});

it('says what a lane holding nothing is empty of', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/orders?lane=done');

    $response->assertSee('Nothing finished yet.');
});

it('reads the oldest parcel first while buyers are waiting', function (): void {
    $seller = $this->seller();
    $older = $this->paidFulfillmentFor($seller);
    $newer = $this->paidFulfillmentFor($seller);
    $older->forceFill(['created_at' => $this->moment('2026-08-18 09:00:00')])->save();
    $newer->forceFill(['created_at' => $this->moment('2026-08-22 09:00:00')])->save();

    $response = $this->actingAs($seller, 'seller')->get('/seller/orders?lane=ship');

    $response->assertSeeInOrder([
        'data-pane-cell="'.$older->id.'"',
        'data-pane-cell="'.$newer->id.'"',
    ], escape: false);
});

it('reads the newest parcel first everywhere else', function (): void {
    $seller = $this->seller();
    $older = $this->paidFulfillmentFor($seller);
    $newer = $this->paidFulfillmentFor($seller);
    $older->forceFill(['created_at' => $this->moment('2026-08-18 09:00:00')])->save();
    $newer->forceFill(['created_at' => $this->moment('2026-08-22 09:00:00')])->save();

    $response = $this->actingAs($seller, 'seller')->get('/seller/orders?lane=all');

    $response->assertSeeInOrder([
        'data-pane-cell="'.$newer->id.'"',
        'data-pane-cell="'.$older->id.'"',
    ], escape: false);
});

it('notes the last step behind a row', function (): void {
    $seller = $this->seller('Luna Lovegood');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $step = FulfillmentFlowStep::factory()->printsLabel()->of($flow, 0)->create(['label' => 'Label printed']);
    $fulfillment = $this->paidFulfillmentFor($seller);
    app(CompleteFlowStep::class)($fulfillment, $step, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller/orders?lane=progress');

    $response->assertSee('Label printed');
});

it('notes what the buyer asked and nobody answered', function (): void {
    $seller = $this->seller('Arthur Weasley');
    $fulfillment = $this->paidFulfillmentFor($seller);
    $conversation = Conversation::create([
        'kind' => ConversationKind::Fulfillment,
        'seller_id' => $seller->id,
        'customer_id' => $fulfillment->customer_id,
        'fulfillment_id' => $fulfillment->id,
        'order_id' => $fulfillment->order_id,
        'last_message_at' => $this->moment('2026-08-22 09:00:00'),
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => ActorType::Customer->value,
        'sender_id' => $fulfillment->customer_id,
        'body' => 'Could you wrap it as a gift?',
        'sent_at' => $this->moment('2026-08-22 09:00:00'),
    ]);

    $response = $this->actingAs($seller, 'seller')->get('/seller/orders?lane=ship');

    $response->assertSee('Could you wrap it as a gift?');
});

it('reads the state line for the status the parcel is in', function (string $state, string $line): void {
    $seller = $this->seller();
    $this->travelTo($this->moment('2026-08-22 09:00:00'));

    $fulfillment = match ($state) {
        'awaiting' => $this->paidFulfillmentFor($seller),
        'shipped' => $this->shippedFulfillmentFor($seller, carrier: 'Owl Post', shippedAt: $this->moment('2026-08-21 14:30:00')),
        default => $this->deliveredFulfillmentFor($seller, deliveredAt: $this->moment('2026-08-21 16:00:00')),
    };

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee($line);
})->with([
    'awaiting shipment' => ['awaiting', 'Placed 2 days ago · ship by Aug 23'],
    'shipped' => ['shipped', 'In transit with Owl Post since Aug 21'],
    'delivered' => ['delivered', 'Delivered Aug 21 · $90.00 released to your balance'],
]);

it('reads the state line off the last step once one is behind the parcel', function (): void {
    $seller = $this->seller('Ginny Weasley');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $step = FulfillmentFlowStep::factory()->printsLabel()->of($flow, 0)->create(['label' => 'Label printed']);
    $fulfillment = $this->paidFulfillmentFor($seller);
    app(CompleteFlowStep::class)($fulfillment, $step, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));
    $this->travelTo($this->moment('2026-08-21 12:00:00'));

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee('Label printed 3 hours ago · waiting for the parcel to leave');
});

it('offers Message buyer whatever state the parcel is in', function (): void {
    $seller = $this->seller();
    $delivered = $this->deliveredFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$delivered->id}");

    $response->assertSee('Message buyer');
    $response->assertSee(route('seller.orders.messages', $delivered), escape: false);
});

it('offers Decline and Mark shipped only while the parcel awaits shipment', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $awaiting = $paidFulfillment($seller);
    $shipped = $this->shippedFulfillmentFor($seller);

    $onAwaiting = $this->actingAs($seller, 'seller')->get("/seller/orders/{$awaiting->id}");
    $onShipped = $this->actingAs($seller, 'seller')->get("/seller/orders/{$shipped->id}");

    $onAwaiting->assertSee('Mark shipped');
    $onAwaiting->assertSee('Decline');
    $onShipped->assertDontSee('Mark shipped');
    $onShipped->assertDontSee('>Decline<', escape: false);
});

it('keeps the action bar on the small screen', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee('data-action-bar', escape: false);
    $response->assertSee('lg:hidden', escape: false);
    $response->assertSeeInOrder(['data-action-bar', 'Message', 'Decline', 'Mark shipped'], escape: false);
});

it('carries the customer, the address, and the money on three cards', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $this->paidFulfillmentFor($seller, $customer, priceCents: 10000);
    $second = $this->paidFulfillmentFor($seller, $customer, priceCents: 45000);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$second->id}");

    $response->assertSee('Customer');
    $response->assertSee('2 orders · $550.00 · since Aug 20, 2026');
    $response->assertSee('Ships to');
    $response->assertSee('12 Analytical Way');
    $response->assertSee('Payment');
    $response->assertSee('Buyer paid');
    $response->assertSee('$450.00');
    $response->assertSee('Your take');
    $response->assertSee('Held until delivery');
});

it('says the money was released once the parcel arrived', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->deliveredFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee('Released to your balance');
});

it('links each item to the listing behind it', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk']);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = $order->fulfillments()->sole();

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee(route('seller.listings.show', $listing), escape: false);
});

it('draws the flow with the next step live and the ones behind it done', function (): void {
    $seller = $this->seller('Molly Weasley');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id, 'name' => 'How I ship']);
    $label = FulfillmentFlowStep::factory()->printsLabel()->of($flow, 0)->create(['label' => 'Label printed']);
    FulfillmentFlowStep::factory()->of($flow, 1)->create(['label' => 'Packed', 'key' => 'packed']);
    $fulfillment = $this->paidFulfillmentFor($seller);
    app(CompleteFlowStep::class)($fulfillment, $label, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee('How I ship');
    $response->assertSeeInOrder(['Label printed', 'Done by Molly Weasley · Aug 21', 'Packed', 'Next']);
});

it('offers the live step only while the parcel is in the studio', function (): void {
    $seller = $this->seller('Luna Lovegood');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    FulfillmentFlowStep::factory()->of($flow, 0)->create(['label' => 'Packed', 'key' => 'packed']);
    $awaiting = $this->paidFulfillmentFor($seller);
    $shipped = $this->shippedFulfillmentFor($seller);

    $onAwaiting = $this->actingAs($seller, 'seller')->get("/seller/orders/{$awaiting->id}");
    $onShipped = $this->actingAs($seller, 'seller')->get("/seller/orders/{$shipped->id}");

    $onAwaiting->assertSee(route('seller.orders.steps.complete', [$awaiting->id, $flow->steps()->sole()->id]), escape: false);
    $onShipped->assertDontSee(route('seller.orders.steps.complete', [$shipped->id, $flow->steps()->sole()->id]), escape: false);
});

it('links the customer card to the customer page', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee('View customer');
    $response->assertSee("/seller/customers/{$fulfillment->customer_id}", escape: false);
});

it('closes the page with everything that happened on the order', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->shippedFulfillmentFor($seller, carrier: 'Owl Post');

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee('Activity');
    $response->assertSee('marked it shipped with Owl Post');
    $response->assertSee('held in escrow after the platform fee');
});

it('narrows the feed to one kind and leaves the rest out', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->shippedFulfillmentFor($seller, carrier: 'Owl Post');

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}?kind=shipping");

    $response->assertSee('marked it shipped with Owl Post');
    $response->assertDontSee('held in escrow after the platform fee');
});

it('says nothing happened once a filter empties the feed', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}?kind=messages");

    $response->assertSee('Nothing has happened on this order yet.');
});

it('opens the pane on the lane the parcel sits in when the link named none', function (): void {
    $seller = $this->seller();
    $delivered = $this->deliveredFulfillmentFor($seller);
    $this->paidFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$delivered->id}");

    $response->assertSee('data-pane-cell="'.$delivered->id.'"', escape: false);
});

it('keeps the lane the row was opened from', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->deliveredFulfillmentFor($seller);
    $waiting = $this->paidFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}?lane=all");

    $response->assertSee('data-pane-cell="'.$waiting->id.'"', escape: false);
});
