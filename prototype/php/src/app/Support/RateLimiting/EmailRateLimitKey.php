<?php

declare(strict_types=1);

namespace App\Support\RateLimiting;

use App\Domain\Auth\EmailNormalizer;

/**
 * The one place an email address becomes a `magic_link_request` rate-limit
 * key, so a call site can only ever reach the two shapes docs/alignment.md
 * §3 fixes: the full digest `RateLimitGate` counts the budget against, and
 * the sixteen-hex-char prefix a `rate_limit.exceed` line is allowed to show
 * (`sha256:<first 16 hex>` of the address, never the address). Neither
 * carries the address itself.
 */
final readonly class EmailRateLimitKey
{
    private const int LOGGED_HEX_LENGTH = 16;

    private function __construct(private string $digest) {}

    public static function for(string $email): self
    {
        return new self(hash('sha256', EmailNormalizer::normalize($email)));
    }

    /**
     * The limiter's bucket identity. The full digest, so an existing
     * deployment's counts do not shift under it.
     */
    public function key(): string
    {
        return "email:{$this->digest}";
    }

    /**
     * What a `rate_limit.exceed` line may show for this key.
     */
    public function logged(): string
    {
        return 'sha256:'.substr($this->digest, 0, self::LOGGED_HEX_LENGTH);
    }
}
