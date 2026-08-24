<?php

declare(strict_types=1);

namespace Tests;

use App\Support\Story;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A unit of work left open by work that threw must not name the next
     * test's lines, so every test starts on an empty stack. The request
     * middleware does the same thing for the same reason.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Story::forget();
    }
}
