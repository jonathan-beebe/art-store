<?php

namespace App\Support\MagicLinkDelivery;

use LogicException;
use PHPUnit\Framework\TestCase;

final class MailMagicLinkDeliveryTest extends TestCase
{
    public function test_it_refuses_to_send_until_email_is_wired_up(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Email delivery is not implemented yet');

        (new MailMagicLinkDelivery)->deliver('artist@example.com', 'http://localhost:8000/auth/magic/abc');
    }
}
