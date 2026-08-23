<?php

declare(strict_types=1);

namespace App\Support\MagicLinkDelivery;

use LogicException;

it('refuses to send until email is wired up', function (): void {
    expect(fn () => (new MailMagicLinkDelivery)->deliver('artist@example.com', 'http://localhost:8000/auth/magic/abc'))
        ->toThrow(LogicException::class, 'Email delivery is not implemented yet');
});
