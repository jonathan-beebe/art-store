<?php

declare(strict_types=1);

namespace App\Domain;

use DomainException;

/**
 * A rule the core refuses: a transition the state machine does not allow, a
 * sale the stock cannot cover, a cart line the listing no longer supports.
 * The message is written for the person who tripped it, because
 * bootstrap/app.php flashes it back to them.
 *
 * Open for a subclass that names more than a message —
 * `App\Domain\Orders\OrderPlacementRefused` is one, folding the blocked
 * lines in through `CarriesRefusalData` so `Story::tell()` can log them
 * without knowing what kind of refusal it caught.
 */
class DomainRuleViolation extends DomainException {}
