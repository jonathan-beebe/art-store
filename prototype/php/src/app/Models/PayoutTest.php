<?php

declare(strict_types=1);

namespace App\Models;

it('reads its amount as money', function (): void {
    $payout = Payout::create([
        'seller_id' => $this->seller()->id,
        'period_start' => '2026-08-10',
        'period_end' => '2026-08-16',
        'amount_cents' => 9000,
        'paid_at' => '2026-08-17 00:00:00',
    ]);

    expect($payout->amount())->toBeMoney(9000);
});

it('narrows the list to one seller, or to everyone when the filter is empty', function (): void {
    $matching = $this->seller('Blue Kiln Studio');
    $other = $this->seller('Rye Press');
    Payout::create(['seller_id' => $matching->id, 'period_start' => '2026-08-10', 'period_end' => '2026-08-16', 'amount_cents' => 9000, 'paid_at' => '2026-08-17 00:00:00']);
    Payout::create(['seller_id' => $other->id, 'period_start' => '2026-08-10', 'period_end' => '2026-08-16', 'amount_cents' => 5000, 'paid_at' => '2026-08-17 00:00:00']);

    expect(Payout::query()->ofSeller($matching->id)->pluck('seller_id')->all())->toBe([$matching->id])
        ->and(Payout::query()->ofSeller(null)->count())->toBe(2);
});
