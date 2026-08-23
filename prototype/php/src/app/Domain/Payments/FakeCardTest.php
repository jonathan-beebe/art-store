<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/*
|--------------------------------------------------------------------------
| preg_replace() override
|--------------------------------------------------------------------------
|
| PHP resolves an unqualified function call from the innermost namespace
| first, so this shadows the built-in for FakeCard alone: normally it
| forwards to the real preg_replace(), letting the test below force the
| null result the real function can return but this suite cannot trigger
| with an ordinary card number.
|
*/
$GLOBALS['fakeCardForcePregReplaceNull'] = false;

function preg_replace($pattern, $replacement, $subject)
{
    return $GLOBALS['fakeCardForcePregReplaceNull'] ? null : \preg_replace($pattern, $replacement, $subject);
}

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

it('ignores dashes', function (): void {
    expect(FakeCard::decide('4242-4242-4242-4242')->isApproved)->toBeTrue();
});

it('ignores no separator at all', function (): void {
    expect(FakeCard::decide('4242424242424242')->isApproved)->toBeTrue();
});

it('ignores a mix of spaces and dashes, including surrounding whitespace', function (): void {
    expect(FakeCard::decide('  4242 4242-4242 4242  ')->isApproved)->toBeTrue();
});

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
