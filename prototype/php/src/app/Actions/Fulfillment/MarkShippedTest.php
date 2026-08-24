<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\DomainRuleViolation;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Events\FulfillmentShipped;
use App\Logging\StoryEvent;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Notifications\OrderShipped;
use App\Support\Story;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\CapturedStory;

$paidOrder = function (Customer $customer): Order {
    return app(FinalizeOrder::class)(
        test()->orderFor($customer, test()->listing(test()->seller(), ['price_cents' => 45000])),
        '4242 4242 4242 4242',
        test()->moment('2026-08-20 10:00:00'),
    );
};

it('records the carrier and the tracking number', function () use ($paidOrder): void {
    $order = $paidOrder($this->verifiedCustomer());
    $fulfillment = $order->fulfillments()->sole();

    $fulfillment = app(MarkShipped::class)($fulfillment, 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    expect($fulfillment->status)->toBe(FulfillmentStatus::Shipped)
        ->and($fulfillment->carrier)->toBe('USPS')
        ->and($fulfillment->tracking_number)->toBe('9400111899')
        ->and($fulfillment->shipped_at?->format('Y-m-d H:i:s'))->toBe('2026-08-21 11:00:00');
});

it('ships the order when its only fulfillment ships', function () use ($paidOrder): void {
    $order = $paidOrder($this->verifiedCustomer());

    app(MarkShipped::class)($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    expect($order)->toHaveStatus(OrderStatus::Shipped);
});

it('partially ships the order when one of two fulfillments ships', function (): void {
    $order = $this->paidOrderWithTwoSellers();

    app(MarkShipped::class)($order->fulfillments()->orderBy('id')->firstOrFail(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    expect($order)->toHaveStatus(OrderStatus::PartiallyShipped);
});

it('tells the customer the order shipped', function () use ($paidOrder): void {
    $customer = $this->verifiedCustomer();
    $order = $paidOrder($customer);
    Notification::fake();

    app(MarkShipped::class)($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    Notification::assertSentTo(
        $customer,
        OrderShipped::class,
        fn (OrderShipped $notification): bool => $notification->toArray($customer)['body']
            === "Order {$order->id} shipped with USPS. Tracking number 9400111899.",
    );
});

it('tells nobody when the shipment is rolled back', function () use ($paidOrder): void {
    $customer = $this->verifiedCustomer();
    $order = $paidOrder($customer);
    $fulfillment = $order->fulfillments()->sole();
    Notification::fake();

    rescue(fn () => DB::transaction(function () use ($fulfillment): void {
        app(MarkShipped::class)($fulfillment, 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

        throw new RuntimeException('the carrier never took it');
    }), report: false);

    Notification::assertNothingSent();
    expect($fulfillment)->toHaveStatus(FulfillmentStatus::AwaitingShipment);
});

it('says the shipment failed when something nobody planned for escapes it', function () use ($paidOrder): void {
    $order = $paidOrder($this->verifiedCustomer());
    $fulfillment = $order->fulfillments()->sole();
    Notification::fake();

    Event::listen(FulfillmentShipped::class, fn () => throw new RuntimeException('the shipment listener broke'));

    $log = CapturedStory::capture();

    $ship = fn () => app(MarkShipped::class)($fulfillment, 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    expect($ship)->toThrow(RuntimeException::class, 'the shipment listener broke');

    $line = $log->line('fulfillment.ship', 'failed');

    expect($line['level'])->toBe('error')
        ->and($line['error'])->toBe(['type' => RuntimeException::class, 'message' => 'the shipment listener broke'])
        ->and($line['data'])->toHaveKey('fulfillment_id', $fulfillment->id);

    // The unit of work the shipment opened is gone, so it cannot name the
    // lines written after it.
    Story::for(StoryEvent::LedgerWrite)->did('wrote a ledger entry');

    expect($log->line('ledger.write', 'did'))->not->toHaveKey('txn_id');
});

it('refuses to ship a fulfillment twice', function () use ($paidOrder): void {
    $order = $paidOrder($this->verifiedCustomer());
    $markShipped = app(MarkShipped::class);
    $fulfillment = $markShipped($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    $markShipped($fulfillment, 'FedEx', '7712349', $this->moment('2026-08-21 12:00:00'));
})->throws(DomainException::class);

it('judges the transition against the row it locks, not the instance it was handed', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller());
    $stale = Fulfillment::query()->findOrFail($fulfillment->id);
    app(DeclineFulfillment::class)($fulfillment, 'Damaged.', $this->moment('2026-08-21 09:00:00'));

    expect(fn () => app(MarkShipped::class)($stale, 'Royal Mail', 'RM999', $this->moment('2026-08-22 09:00:00')))
        ->toThrow(DomainRuleViolation::class, 'declined to shipped');

    expect($fulfillment->fresh()?->status)->toBe(FulfillmentStatus::Declined);
});
