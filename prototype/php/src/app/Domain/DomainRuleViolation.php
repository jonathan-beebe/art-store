<?php

declare(strict_types=1);

namespace App\Domain;

use DomainException;

/**
 * A rule the core refuses: a transition the state machine does not allow, a
 * sale the stock cannot cover, a cart line the listing no longer supports.
 * The message is written for the person who tripped it, because
 * bootstrap/app.php flashes it back to them.
 */
final class DomainRuleViolation extends DomainException {}
