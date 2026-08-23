<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

it('names the two sides of the marketplace', function (): void {
    expect(array_column(RecipientType::cases(), 'value'))->toBe(['seller', 'customer']);
});
