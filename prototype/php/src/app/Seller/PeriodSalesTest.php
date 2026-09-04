<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Fulfillment\DeclineFulfillment;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Escrow\PayoutPeriod;
use App\Domain\Orders\FulfillmentStatus;

it('lists every order placed inside the period, newest first, whatever its status', function (): void {
    $seller = $this->seller();
    $older = $this->paidFulfillmentFor($seller, priceCents: 5_000);
    $older->order()->update(['placed_at' => $this->moment('2026-08-11 09:00:00')]);
    $newer = $this->paidFulfillmentFor($seller, priceCents: 7_000);
    $newer->order()->update(['placed_at' => $this->moment('2026-08-14 09:00:00')]);
    app(DeclineFulfillment::class)($newer->refresh(), 'Buyer changed their mind.', $this->moment('2026-08-15 09:00:00'));

    $period = PayoutPeriod::endingBefore($this->moment('2026-08-17 00:00:00'));
    $rows = PeriodSales::for($seller, $period);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->fulfillmentId)->toBe($newer->id)
        ->and($rows[0]->status)->toBe(FulfillmentStatus::Declined)
        ->and($rows[1]->fulfillmentId)->toBe($older->id);
});

it('leaves out orders placed outside the period', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    $fulfillment->order()->update(['placed_at' => $this->moment('2026-08-01 09:00:00')]);

    $period = PayoutPeriod::endingBefore($this->moment('2026-08-17 00:00:00'));

    expect(PeriodSales::for($seller, $period))->toBeEmpty();
});

it('reports each row buyer, item, subtotal, fee, and net', function (): void {
    $seller = $this->seller();
    $order = $this->orderFor(
        $this->verifiedCustomer(),
        $this->listing($seller, ['title' => 'Nine Owls', 'price_cents' => 10_000]),
    );
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $order->update(['placed_at' => $this->moment('2026-08-11 09:00:00')]);

    $period = PayoutPeriod::endingBefore($this->moment('2026-08-17 00:00:00'));
    [$row] = PeriodSales::for($seller, $period);

    expect($row->buyerName)->toBe($order->refresh()->shipping_name)
        ->and($row->itemLabel)->toBe('Nine Owls')
        ->and($row->subtotal->format())->toBe('$100.00')
        ->and($row->fee->format())->toBe('$10.00')
        ->and($row->net->format())->toBe('$90.00');
});
