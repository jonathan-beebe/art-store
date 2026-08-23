<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use DateTimeImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * The shell's one clock. Domain code receives the instant it produces;
     * nothing under app/Domain reads a clock of its own.
     */
    protected function now(): DateTimeImmutable
    {
        return now()->toDateTimeImmutable();
    }
}
