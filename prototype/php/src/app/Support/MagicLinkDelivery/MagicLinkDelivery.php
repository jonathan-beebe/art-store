<?php

namespace App\Support\MagicLinkDelivery;

interface MagicLinkDelivery
{
    public function deliver(string $email, string $url): void;
}
