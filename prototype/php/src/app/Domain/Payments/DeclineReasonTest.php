<?php

declare(strict_types=1);

namespace App\Domain\Payments;

it('reads back as a sentence for the checkout page', function (DeclineReason $reason, string $expected): void {
    expect($reason->message())->toBe($expected);
})->with([
    'generic decline' => [DeclineReason::GenericDecline, 'Your card was declined.'],
    'insufficient funds' => [DeclineReason::InsufficientFunds, 'Your card has insufficient funds.'],
    'invalid card number' => [DeclineReason::InvalidCardNumber, 'That card number is not valid.'],
]);
