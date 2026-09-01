<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;

it('mints a prefixed id', function (): void {
    expect(Refund::factory()->create()->id)->toStartWith('rfd_');
});

it('reads its amount as money', function (): void {
    expect(Refund::factory()->create(['amount_cents' => 12500])->amount())->toBeMoney(12500);
});

it('reads back who issued it', function (bool $byAdmin, ActorType $expectedIssuer, string $expectedLabel): void {
    $refund = $byAdmin
        ? Refund::factory()->byAdmin($this->admin()->id)->create()
        : Refund::factory()->create();

    expect($refund->issuer())->toBe($expectedIssuer)
        ->and($refund->issuerLabel())->toBe($expectedLabel);
})->with([
    'admin' => [true, ActorType::Admin, 'Admin'],
    'seller' => [false, ActorType::Seller, 'Seller'],
]);
