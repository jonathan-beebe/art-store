<?php

declare(strict_types=1);

namespace App\Domain\Seller;

it('names the fixed window listings and customers read', function (): void {
    expect(EvergreenWindow::DAYS)->toBe(30);
});
