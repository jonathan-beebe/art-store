<?php

declare(strict_types=1);

namespace App\Domain\Seller;

it('labels every case', function (ActivityKind $kind, string $label): void {
    expect($kind->label())->toBe($label);
})->with([
    'browse' => [ActivityKind::Browse, 'Browsing'],
    'order' => [ActivityKind::Order, 'Order'],
    'shipping' => [ActivityKind::Shipping, 'Shipping'],
    'messages' => [ActivityKind::Messages, 'Messages'],
]);
