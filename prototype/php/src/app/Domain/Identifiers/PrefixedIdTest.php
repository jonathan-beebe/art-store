<?php

declare(strict_types=1);

namespace App\Domain\Identifiers;

use App\Domain\DomainRuleViolation;

it('spells an id from a prefix and a ULID body', function (): void {
    $id = PrefixedId::of('ord', '01J5X3M9A2K8YB7Q4R6T1V0WZE');

    expect($id->prefix)->toBe('ord')
        ->and($id->ulid)->toBe('01J5X3M9A2K8YB7Q4R6T1V0WZE')
        ->and((string) $id)->toBe('ord_01J5X3M9A2K8YB7Q4R6T1V0WZE')
        ->and(strlen((string) $id))->toBe(PrefixedId::LENGTH);
});

it('refuses to spell an id from a prefix that is not three lowercase letters', function (string $prefix): void {
    expect(fn () => PrefixedId::of($prefix, '01J5X3M9A2K8YB7Q4R6T1V0WZE'))
        ->toThrow(DomainRuleViolation::class);
})->with(['', 'or', 'order', 'ORD', 'or1']);

it('refuses to spell an id from a body that is not an uppercase ULID', function (string $ulid): void {
    expect(fn () => PrefixedId::of('ord', $ulid))->toThrow(DomainRuleViolation::class);
})->with([
    'the empty string' => '',
    'twenty-five characters' => '01J5X3M9A2K8YB7Q4R6T1V0WZ',
    'a lowercase body' => '01j5x3m9a2k8yb7q4r6t1v0wze',
    'a body past the ULID time range' => '81J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a letter Crockford base32 drops' => '01J5X3M9A2K8YB7Q4R6T1V0WZI',
]);

it('reads back the id of the table it is asked about', function (): void {
    $id = PrefixedId::parse('ord', 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZE');

    expect($id)->not->toBeNull()
        ->and((string) $id)->toBe('ord_01J5X3M9A2K8YB7Q4R6T1V0WZE');
});

it('reads a hand-written seed id', function (): void {
    expect((string) PrefixedId::parse('ord', 'ord_00000000000000000000000001'))
        ->toBe('ord_00000000000000000000000001');
});

it('names no row of the table when the value is not one of its ids', function (string $value): void {
    expect(PrefixedId::parse('ord', $value))->toBeNull();
})->with([
    'another table prefix' => 'cus_01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'no prefix' => 'ord01J5X3M9A2K8YB7Q4R6T1V0WZEE',
    'a bare ULID' => '01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'too short' => 'ord_1J5X3M9A2K8YB7Q4R6T1V0WZE',
    'too long' => 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZEE',
    'a lowercase body' => 'ord_01j5x3m9a2k8yb7q4r6t1v0wze',
    'a letter Crockford base32 drops' => 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZL',
    'a body past the ULID time range' => 'ord_81J5X3M9A2K8YB7Q4R6T1V0WZE',
    'the empty string' => '',
    'a sentence' => 'the quick brown fox jumps over',
]);

it('names no row when the prefix asked about is not one an id can carry', function (): void {
    expect(PrefixedId::parse('o1d', 'o1d_01J5X3M9A2K8YB7Q4R6T1V0WZE'))->toBeNull();
});
