<?php

namespace App\Support\MagicLinkDelivery;

use LogicException;

final readonly class MailMagicLinkDelivery implements MagicLinkDelivery
{
    public function deliver(string $email, string $url): void
    {
        throw new LogicException('Email delivery is not implemented yet');
    }
}
