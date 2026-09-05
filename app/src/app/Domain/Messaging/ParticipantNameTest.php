<?php

declare(strict_types=1);

use App\Domain\Messaging\ParticipantName;

it('names a customer by their given name', function (): void {
    expect(ParticipantName::forCustomer('Luna Lovegood', 'cus_01'))->toBe('Luna Lovegood');
});

it('names a customer with no given name by their id', function (): void {
    expect(ParticipantName::forCustomer(null, 'cus_01'))->toBe('Customer cus_01');
});

it('names the desk and a deleted account by one constant each', function (): void {
    expect(ParticipantName::DESK)->toBe('Art Store Support')
        ->and(ParticipantName::DELETED)->toBe('Deleted account');
});
