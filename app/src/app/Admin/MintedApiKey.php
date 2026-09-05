<?php

declare(strict_types=1);

namespace App\Admin;

use App\Models\ApiKey;

/**
 * What minting hands back: the stored row and the one plaintext showing
 * of its token. Nothing keeps the plaintext after this value is gone.
 */
final readonly class MintedApiKey
{
    public function __construct(
        public ApiKey $key,
        public string $plaintext,
    ) {}
}
