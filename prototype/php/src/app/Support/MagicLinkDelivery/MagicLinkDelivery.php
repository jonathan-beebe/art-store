<?php

declare(strict_types=1);

namespace App\Support\MagicLinkDelivery;

interface MagicLinkDelivery
{
    public function deliver(string $email, string $url): void;
}
