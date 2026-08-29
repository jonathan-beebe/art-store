<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\DomainRuleViolation;

it('allows a mode change when the axis has no options', function (): void {
    PricingModeChangeGuard::forAxis(changingMode: true, hasOptions: false);
})->throwsNoExceptions();

it('allows an update that keeps the same mode even with options present', function (): void {
    PricingModeChangeGuard::forAxis(changingMode: false, hasOptions: true);
})->throwsNoExceptions();

it('refuses a mode change once the axis has an option', function (): void {
    PricingModeChangeGuard::forAxis(changingMode: true, hasOptions: true);
})->throws(DomainRuleViolation::class, "This choice already has options — its pricing can't change. Remove the options first, or add a new choice.");
