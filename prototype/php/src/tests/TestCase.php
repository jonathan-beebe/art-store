<?php

declare(strict_types=1);

namespace Tests;

use App\Support\Story;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * `RefreshDatabase` migrates and wraps a per-test transaction only on
     * the connections named here — `null` for the default (commerce)
     * connection, `'analytics'` for the analytics store
     * (config/database.php). Left off this list, the analytics connection
     * would migrate once on the first test that touches it and every test
     * after would see whatever that first test committed, since nothing
     * would roll its writes back or restore its in-memory PDO between
     * tests.
     *
     * @var list<string|null>
     */
    protected array $connectionsToTransact = [null, 'analytics'];

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
