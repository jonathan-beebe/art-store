<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\DomainRuleViolation;

it('allows deleting an axis no variant references', function (): void {
    ConfiguratorDeletionGuard::forAxis(false);
})->throwsNoExceptions();

it('refuses to delete an axis a variant references', function (): void {
    ConfiguratorDeletionGuard::forAxis(true);
})->throws(DomainRuleViolation::class);

it('allows deleting an option value no variant references', function (): void {
    ConfiguratorDeletionGuard::forOptionValue(false);
})->throwsNoExceptions();

it('refuses to delete an option value a variant references', function (): void {
    ConfiguratorDeletionGuard::forOptionValue(true);
})->throws(DomainRuleViolation::class);
