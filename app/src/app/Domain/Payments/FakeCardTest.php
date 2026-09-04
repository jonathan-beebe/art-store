<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/*
|--------------------------------------------------------------------------
| preg_replace() override
|--------------------------------------------------------------------------
|
| tests/FunctionOverrides.php declares the App\Domain\Payments\preg_replace()
| override this file forces below; see that file for why it lives there
| rather than here.
|
*/

it('treats a preg_replace failure as an invalid, empty-numbered card', function (): void {
    $GLOBALS['fakeCardForcePregReplaceNull'] = true;

    try {
        $decision = FakeCard::decide('4242424242424242');
    } finally {
        $GLOBALS['fakeCardForcePregReplaceNull'] = false;
    }

    expect($decision->isApproved)->toBeFalse()
        ->and($decision->lastFour)->toBe('')
        ->and($decision->declineReason)->toBe(DeclineReason::InvalidCardNumber);
});

it('decides approval or a decline reason from the card number', function (string $number, ?DeclineReason $expected): void {
    $decision = FakeCard::decide($number);

    expect($decision->isApproved)->toBe($expected === null)
        ->and($decision->declineReason)->toBe($expected);
})->with([
    '4242 4242 4242 4242 is approved' => ['4242 4242 4242 4242', null],
    '4000 0000 0000 0002 is declined: generic decline' => ['4000 0000 0000 0002', DeclineReason::GenericDecline],
    '4000 0000 0000 9995 is declined: insufficient funds' => ['4000 0000 0000 9995', DeclineReason::InsufficientFunds],
    'anything else is declined: invalid card number' => ['1234 5678 9012 3456', DeclineReason::InvalidCardNumber],
]);

it('ignores separators between the digits', function (string $number): void {
    expect(FakeCard::decide($number)->isApproved)->toBeTrue();
})->with([
    'dashes' => ['4242-4242-4242-4242'],
    'no separator at all' => ['4242424242424242'],
    'a mix of spaces and dashes, including surrounding whitespace' => ['  4242 4242-4242 4242  '],
]);

it('exposes the last four digits', function (): void {
    expect(FakeCard::decide('4000-0000-0000-9995')->lastFour)->toBe('9995');
});

it('exposes every digit of a number shorter than four', function (): void {
    expect(FakeCard::decide('42')->lastFour)->toBe('42');
});

it('declines an empty number as invalid', function (): void {
    $decision = FakeCard::decide('   ');

    expect($decision->isApproved)->toBeFalse()
        ->and($decision->lastFour)->toBe('')
        ->and($decision->declineReason)->toBe(DeclineReason::InvalidCardNumber);
});
