<?php

declare(strict_types=1);

namespace App\Support\MagicLinkDelivery;

use LogicException;
use Override;

final readonly class MailMagicLinkDelivery implements MagicLinkDelivery
{
    #[Override]
    public function deliver(string $email, string $url): void
    {
        throw new LogicException('Email delivery is not implemented yet');
    }
}
