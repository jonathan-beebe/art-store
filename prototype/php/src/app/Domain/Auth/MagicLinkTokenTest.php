<?php

namespace App\Domain\Auth;

use PHPUnit\Framework\TestCase;

final class MagicLinkTokenTest extends TestCase
{
    public function test_it_hashes_a_token_with_sha256(): void
    {
        $this->assertSame(hash('sha256', 'abc'), MagicLinkToken::hash('abc'));
    }

    public function test_it_hashes_the_same_token_to_the_same_digest(): void
    {
        $this->assertSame(MagicLinkToken::hash('abc'), MagicLinkToken::hash('abc'));
    }

    public function test_it_hashes_different_tokens_to_different_digests(): void
    {
        $this->assertNotSame(MagicLinkToken::hash('abc'), MagicLinkToken::hash('abd'));
    }
}
