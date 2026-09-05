<?php

declare(strict_types=1);

namespace App\Domain\Auth;

it('recognises a prefixed forty-character secret', function (): void {
    expect(ApiKeyToken::isWellFormed('artstore_'.str_repeat('a1B2', 10)))->toBeTrue();
});

it('rejects a token of any other shape', function (string $token): void {
    expect(ApiKeyToken::isWellFormed($token))->toBeFalse();
})->with([
    'no prefix' => [str_repeat('a1B2', 10)],
    'wrong prefix' => ['artstore-'.str_repeat('a1B2', 10)],
    'too short' => ['artstore_'.str_repeat('a1B2', 9)],
    'too long' => ['artstore_'.str_repeat('a1B2', 11)],
    'punctuation' => ['artstore_'.str_repeat('a1B2', 9).'a-1='],
    'empty' => [''],
]);

it('hashes a token to its sha256 hex digest', function (): void {
    expect(ApiKeyToken::hash('artstore_secret'))->toBe(hash('sha256', 'artstore_secret'))
        ->and(ApiKeyToken::hash('artstore_secret'))->not->toBe(ApiKeyToken::hash('artstore_other'));
});
