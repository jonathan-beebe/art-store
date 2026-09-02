<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

/**
 * One stat tile on the event page — "This range", "Previous", "Change",
 * "Busiest day", "Distinct actors" — in the admin chrome's
 * `label`/`value`/`note` stat-tile shape. {@see EventDetail::forRange()}
 * builds the five the page shows.
 */
final readonly class EventTile
{
    public function __construct(
        public string $label,
        public string $value,
        public string $note,
    ) {}
}
