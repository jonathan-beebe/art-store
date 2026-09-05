<?php

declare(strict_types=1);

namespace App\Domain\Seller;

it('reads a name as up to two initials', function (string $name, string $expected): void {
    expect(Initials::of($name))->toBe($expected);
})->with([
    'two words' => ['Luna Lovegood', 'LL'],
    'three words' => ['Nymphadora Andromeda Tonks', 'NA'],
    'one word' => ['Hagrid', 'H'],
    'padded' => ['  Ginny   Weasley  ', 'GW'],
    'no name at all' => ['', ''],
]);
