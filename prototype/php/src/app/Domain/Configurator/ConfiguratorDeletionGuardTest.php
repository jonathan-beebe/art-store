<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\DomainRuleViolation;

it('allows deleting an axis no variant references', function (): void {
    ConfiguratorDeletionGuard::forAxis(false);
})->throwsNoExceptions();

it('refuses to delete an axis a variant references, naming where to remove it', function (): void {
    ConfiguratorDeletionGuard::forAxis(true);
})->throws(DomainRuleViolation::class, 'This axis has a variant built from one of its values; remove or reassign that variant on Combinations & stock first.');

it('allows deleting an option value no variant references', function (): void {
    ConfiguratorDeletionGuard::forOptionValue(false);
})->throwsNoExceptions();

it('refuses to delete an option value a variant references, naming where to remove it', function (): void {
    ConfiguratorDeletionGuard::forOptionValue(true);
})->throws(DomainRuleViolation::class, 'This option value is selected by a variant; remove that variant on Combinations & stock first.');

it('allows deleting a variant no cart or order references', function (): void {
    ConfiguratorDeletionGuard::forVariant(false);
})->throwsNoExceptions();

it('refuses to delete a variant a cart or order references, naming what to do instead', function (): void {
    ConfiguratorDeletionGuard::forVariant(true);
})->throws(DomainRuleViolation::class, 'This combination is in a cart or an order; turn off "Offered" instead of removing it.');
