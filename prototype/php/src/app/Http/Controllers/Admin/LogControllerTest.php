<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Logging\LogStore;
use Tests\LogViewerFixtures as Fixtures;

it('sends a guest to the admin login page from the list and the story view', function (): void {
    $this->get('/admin/logs?domain=')->assertRedirect(route('auth.admin.login'));
    $this->get('/admin/logs/requests/req_1')->assertRedirect(route('auth.admin.login'));
});

it('redirects a query-string-less landing to the default domain, grouped', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs');

    $response->assertRedirect(route('admin.logs.index', ['domain' => 'shop', 'group' => 1]));
});

it('does not redirect when any query parameter is present, even an empty one', function (string $query): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/logs?{$query}");

    $response->assertOk();
})->with([
    'an empty domain' => 'domain=',
    'an unrelated param' => 'page=1',
    'the canonical default itself' => 'domain=shop&group=1',
]);

it('renders a friendly unavailable state on both pages when the store is off', function (): void {
    // LOG_DATABASE_FILE=off for the whole suite (phpunit.xml), so the
    // container's default LogStore is already disabled here.
    $index = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?domain=');
    $index->assertOk()->assertSee('log store is unavailable');

    $story = $this->actingAs($this->admin(), 'admin')->get('/admin/logs/requests/req_1');
    $story->assertOk()->assertSee('log store is unavailable');
});

it('lists lines newest first and tints warn and failed rows', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['ts' => '2026-08-24T12:00:00.000Z', 'msg' => 'an ordinary line', 'level' => 'info']),
        Fixtures::line(['ts' => '2026-08-24T12:00:01.000Z', 'msg' => 'a warning line', 'level' => 'warn']),
        Fixtures::line(['ts' => '2026-08-24T12:00:02.000Z', 'msg' => 'a failed line', 'level' => 'error', 'phase' => 'failed']),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?domain=');

    $response->assertOk()
        ->assertSeeInOrder(['a failed line', 'a warning line', 'an ordinary line'])
        ->assertSee('data-severity="error"', false)
        ->assertSee('data-severity="warn"', false)
        ->assertSee('data-severity="none"', false)
        ->assertSee('bg-red-50', false)
        ->assertSee('bg-amber-50', false);
});

it('narrows the list by level and marks the level chip current', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['msg' => 'an info line', 'level' => 'info']),
        Fixtures::line(['msg' => 'a warning line', 'level' => 'warn']),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?level=warn');

    $response->assertOk()
        ->assertSee('a warning line')
        ->assertDontSee('an info line');

    $html = (string) $response->getContent();
    expect($html)->toMatch('/data-stat="level-warn"\s+aria-current="true"/');
});

it('shows the four level tiles with counts and links that set the level filter', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['level' => 'error', 'phase' => 'failed']),
        Fixtures::line(['level' => 'warn']),
        Fixtures::line(['level' => 'warn']),
        Fixtures::line(['level' => 'info']),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?domain=');

    $response->assertOk()
        ->assertSee('data-stat="level-error"', false)
        ->assertSee('data-stat="level-warn"', false)
        ->assertSee('data-stat="level-info"', false)
        ->assertSee('data-stat="level-debug"', false)
        ->assertSee(route('admin.logs.index', ['level' => 'warn']), false);

    $html = $response->getContent();
    expect($html)->not->toBeFalse();
    preg_match('/data-stat="level-warn".*?data-count[^>]*>(\d+)</s', (string) $html, $match);
    expect($match[1] ?? null)->toBe('2');
});

it('tallies the tiles over the filters minus level itself', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['level' => 'warn', 'event' => 'order.pay']),
        Fixtures::line(['level' => 'error', 'phase' => 'failed', 'event' => 'order.pay']),
        Fixtures::line(['level' => 'warn', 'event' => 'order.place']),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?level=error&event=order.pay');

    $html = (string) $response->getContent();
    preg_match('/data-stat="level-warn".*?data-count[^>]*>(\d+)</s', $html, $match);
    expect($match[1] ?? null)->toBe('1');
});

it('hides health-check lines by default and shows them with health=1', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['request_id' => 'req_health', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /up', 'data' => ['method' => 'GET', 'path' => '/up']]),
        Fixtures::line(['request_id' => 'req_health', 'event' => 'http.request', 'phase' => 'did', 'msg' => 'GET /up 200', 'data' => ['status' => 200]]),
        Fixtures::line(['msg' => 'an ordinary line']),
    ]);
    $this->app->instance(LogStore::class, $store);

    $hidden = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?domain=');
    $hidden->assertOk()->assertDontSee('GET /up 200');

    $shown = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?health=1');
    $shown->assertOk()->assertSee('GET /up 200');
});

it('hides the log viewers own requests by default and shows them with viewer=1', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['request_id' => 'req_viewer', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /admin/logs', 'data' => ['method' => 'GET', 'path' => '/admin/logs']]),
        Fixtures::line(['request_id' => 'req_viewer', 'event' => 'http.request', 'phase' => 'did', 'msg' => 'GET /admin/logs 200', 'data' => ['status' => 200]]),
        Fixtures::line(['msg' => 'an ordinary line']),
    ]);
    $this->app->instance(LogStore::class, $store);

    $hidden = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?domain=');
    $hidden->assertOk()->assertDontSee('GET /admin/logs 200');

    $shown = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?viewer=1');
    $shown->assertOk()->assertSee('GET /admin/logs 200');
});

it('hides viewer requests under domain=admin even though they share the admin domain', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['request_id' => 'req_viewer', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /admin/logs', 'data' => ['method' => 'GET', 'path' => '/admin/logs']]),
        Fixtures::line(['request_id' => 'req_admin_orders', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /admin/orders', 'data' => ['method' => 'GET', 'path' => '/admin/orders']]),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?domain=admin');

    $response->assertOk()->assertSee('GET /admin/orders')->assertDontSee('GET /admin/logs');
});

it('keeps a hidden viewer requests story addressable by id — the story view ignores the filter', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['request_id' => 'req_viewer', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /admin/logs', 'data' => ['method' => 'GET', 'path' => '/admin/logs']]),
        Fixtures::line(['request_id' => 'req_viewer', 'event' => 'http.request', 'phase' => 'did', 'msg' => 'GET /admin/logs 200', 'duration_ms' => 5, 'data' => ['status' => 200]]),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs/requests/req_viewer');

    $response->assertOk()->assertSee('GET /admin/logs 200');
});

it('narrows the list by domain', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['request_id' => 'req_admin', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /admin/orders', 'data' => ['method' => 'GET', 'path' => '/admin/orders']]),
        Fixtures::line(['request_id' => 'req_shop', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /checkout', 'data' => ['method' => 'GET', 'path' => '/checkout']]),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?domain=shop');

    $response->assertOk()->assertSee('GET /checkout')->assertDontSee('GET /admin/orders');
});

it('narrows the list with the any-attribute filter', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['msg' => 'has a refund', 'data' => ['refund_id' => 'rfd_1']]),
        Fixtures::line(['msg' => 'no refund here']),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?key=data.refund_id');

    $response->assertOk()->assertSee('has a refund')->assertDontSee('no refund here');
});

it('paginates 50 to a page, newest first, with filters carried through the pager', function (): void {
    $lines = [];
    for ($i = 0; $i < 55; $i++) {
        $lines[] = Fixtures::line(['ts' => sprintf('2026-08-24T00:00:%02d.000Z', $i), 'msg' => sprintf('line %03d', $i)]);
    }
    $store = Fixtures::store($lines);
    $this->app->instance(LogStore::class, $store);

    $page1 = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?domain=');
    $page1->assertOk()->assertSee('Page 1 of 2')->assertSee('line 054')->assertDontSee('line 004');
    expect(substr_count((string) $page1->getContent(), 'data-line="'))->toBe(50);

    $page2 = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?page=2');
    $page2->assertOk()->assertSee('Page 2 of 2')->assertSee('line 000')->assertDontSee('line 054');
    expect(substr_count((string) $page2->getContent(), 'data-line="'))->toBe(5);
});

it('groups by request and tints the group by its worst line', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['request_id' => 'req_1', 'ts' => '2026-08-24T09:00:00.000Z', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /checkout', 'data' => ['method' => 'GET', 'path' => '/checkout']]),
        Fixtures::line(['request_id' => 'req_1', 'ts' => '2026-08-24T09:00:00.010Z', 'event' => 'http.request', 'phase' => 'failed', 'msg' => 'GET /checkout broke', 'level' => 'error', 'duration_ms' => 10, 'data' => ['status' => 500]]),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?group=1');

    $response->assertOk()
        ->assertSee('data-group="req_1"', false)
        ->assertSee('data-severity="error"', false)
        ->assertSee(route('admin.logs.story', ['requestId' => 'req_1']), false)
        ->assertSee('GET /checkout broke')
        ->assertSee('data-cell="line-count"', false);

    $html = (string) $response->getContent();
    preg_match('/data-cell="line-count"[^>]*>\s*(\d+)/s', $html, $match);
    expect($match[1] ?? null)->toBe('2');
});

it('tints a group yellow when its worst line only warns, never fails', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['request_id' => 'req_1', 'ts' => '2026-08-24T09:00:00.000Z', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /checkout', 'data' => ['method' => 'GET', 'path' => '/checkout']]),
        Fixtures::line(['request_id' => 'req_1', 'ts' => '2026-08-24T09:00:00.005Z', 'level' => 'warn', 'msg' => 'a warning line']),
        Fixtures::line(['request_id' => 'req_1', 'ts' => '2026-08-24T09:00:00.010Z', 'event' => 'http.request', 'phase' => 'did', 'msg' => 'GET /checkout 200', 'duration_ms' => 10, 'data' => ['status' => 200]]),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?group=1');

    $response->assertOk()->assertSee('data-severity="warn"', false)->assertSee('bg-amber-50', false);
});

it('shows the empty state when no line matches the filters', function (): void {
    $store = Fixtures::store([Fixtures::line(['msg' => 'an ordinary line'])]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?msg=nothing-matches-this');

    $response->assertOk()->assertSee('No log lines match these filters.');
});

it('renders the story header from the root will/did pair, and links session and actor', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['request_id' => 'req_1', 'ts' => '2026-08-24T09:00:00.000Z', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /checkout', 'session_id' => 'ses_01J5X3M9A2K8YB7Q4R6T1V0WZE', 'actor_type' => 'customer', 'actor_id' => 'cus_01J5X3M9A2K8YB7Q4R6T1V0WZE', 'data' => ['method' => 'GET', 'path' => '/checkout']]),
        Fixtures::line(['request_id' => 'req_1', 'ts' => '2026-08-24T09:00:00.020Z', 'event' => 'http.request', 'phase' => 'did', 'msg' => 'GET /checkout 200', 'duration_ms' => 20, 'data' => ['status' => 200]]),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs/requests/req_1');

    $response->assertOk()
        ->assertSee('data-stat="lines"', false)
        ->assertSee('2026-08-24T09:00:00.000Z')
        ->assertSee('2026-08-24T09:00:00.020Z')
        ->assertSee(route('admin.customers.show', ['cus_01J5X3M9A2K8YB7Q4R6T1V0WZE']), false)
        ->assertSee('View customer')
        ->assertSee(route('admin.logs.index', ['actor' => 'cus_01J5X3M9A2K8YB7Q4R6T1V0WZE']), false)
        ->assertSee(route('admin.logs.index', ['session' => 'ses_01J5X3M9A2K8YB7Q4R6T1V0WZE']), false);

    $html = (string) $response->getContent();
    preg_match('/data-stat="duration"[^>]*>\s*(\d+)/s', $html, $match);
    expect($match[1] ?? null)->toBe('20');
});

it('reads the root close duration from a failed close the same as a did close', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['request_id' => 'req_1', 'ts' => '2026-08-24T09:00:00.000Z', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /checkout', 'data' => ['method' => 'GET', 'path' => '/checkout']]),
        Fixtures::line(['request_id' => 'req_1', 'ts' => '2026-08-24T09:00:00.030Z', 'event' => 'http.request', 'phase' => 'failed', 'msg' => 'GET /checkout broke', 'level' => 'error', 'duration_ms' => 30]),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs/requests/req_1');

    $response->assertOk()->assertSee('data-severity="error"', false)->assertSee('bg-red-50', false);
    $html = (string) $response->getContent();
    preg_match('/data-stat="duration"[^>]*>\s*(\d+)/s', $html, $match);
    expect($match[1] ?? null)->toBe('30');
});

it('renders the empty state at 200 for a well-formed request id with no stored lines', function (): void {
    $store = Fixtures::store([Fixtures::line(['request_id' => 'req_other'])]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs/requests/req_missing');

    $response->assertOk()->assertSee('No lines are stored for this request.');
});

it('answers the sites standard 404 for a malformed request id', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs/requests/'.rawurlencode('bad id!'));

    $response->assertNotFound();
});

it('shows a cap notice past 1000 stored lines', function (): void {
    $lines = [];
    for ($i = 0; $i < 1005; $i++) {
        $lines[] = Fixtures::line(['request_id' => 'req_many', 'ts' => sprintf('2026-08-24T%02d:%02d:%02d.000Z', intdiv($i, 3600), intdiv($i, 60) % 60, $i % 60), 'msg' => "line {$i}"]);
    }
    $store = Fixtures::store($lines);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs/requests/req_many');

    $response->assertOk()->assertSee('Showing the first 1000 of 1005 lines.');
});

it('links a prefixed id inside a disclosed data block', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['msg' => 'placed the order', 'data' => ['order_id' => 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZE']]),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?domain=');

    $response->assertOk()->assertSee(route('admin.orders.show', ['ord_01J5X3M9A2K8YB7Q4R6T1V0WZE']), false);
});

it('renders a rows request id as a filter link carrying an existing filter, plus a story chevron', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['request_id' => 'req_1', 'msg' => 'a warning line', 'level' => 'warn']),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?level=warn');

    $response->assertOk()
        ->assertSee(e(route('admin.logs.index', ['level' => 'warn', 'request' => 'req_1'])), false)
        ->assertSee('aria-label="Open request story for req_1"', false)
        ->assertSee(route('admin.logs.story', ['requestId' => 'req_1']), false);
});

it('renders a rows actor id as a filter link and a separate View control to its detail page', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['actor_type' => 'customer', 'actor_id' => 'cus_01J5X3M9A2K8YB7Q4R6T1V0WZE']),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?domain=');

    $response->assertOk()
        ->assertSee(route('admin.logs.index', ['actor' => 'cus_01J5X3M9A2K8YB7Q4R6T1V0WZE']), false)
        ->assertSee(route('admin.customers.show', ['cus_01J5X3M9A2K8YB7Q4R6T1V0WZE']), false)
        ->assertSee('View customer');
});

it('omits the actor View control when the actors prefix has no detail page', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['actor_type' => 'admin', 'actor_id' => 'adm_01J5X3M9A2K8YB7Q4R6T1V0WZE']),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?domain=');

    $response->assertOk()
        ->assertSee(route('admin.logs.index', ['actor' => 'adm_01J5X3M9A2K8YB7Q4R6T1V0WZE']), false)
        ->assertDontSee('View admin');
});

it('tucks a rows txn and session ids into its ids disclosure when the row does not already show them', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['txn_id' => 'txn_01J5X3M9A2K8YB7Q4R6T1V0WZE', 'session_id' => 'ses_01J5X3M9A2K8YB7Q4R6T1V0WZE']),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?domain=');

    $response->assertOk()
        ->assertSee('<summary class="cursor-pointer text-gray-500">ids</summary>', false)
        ->assertSee(route('admin.logs.index', ['txn' => 'txn_01J5X3M9A2K8YB7Q4R6T1V0WZE']), false)
        ->assertSee(route('admin.logs.index', ['session' => 'ses_01J5X3M9A2K8YB7Q4R6T1V0WZE']), false);
});

it('shows a request filter link and a story chevron on the grouped view', function (): void {
    $store = Fixtures::store([
        Fixtures::line(['request_id' => 'req_1', 'ts' => '2026-08-24T09:00:00.000Z', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /checkout', 'data' => ['method' => 'GET', 'path' => '/checkout']]),
        Fixtures::line(['request_id' => 'req_1', 'ts' => '2026-08-24T09:00:00.010Z', 'event' => 'http.request', 'phase' => 'did', 'msg' => 'GET /checkout 200', 'duration_ms' => 10, 'data' => ['status' => 200]]),
    ]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs?group=1&domain=shop');

    $response->assertOk()
        ->assertSee(e(route('admin.logs.index', ['domain' => 'shop', 'group' => '1', 'request' => 'req_1'])), false)
        ->assertSee('aria-label="Open request story for req_1"', false);
});

it('links the story views request id in the header back into the filtered list', function (): void {
    $store = Fixtures::store([Fixtures::line(['request_id' => 'req_1'])]);
    $this->app->instance(LogStore::class, $store);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/logs/requests/req_1');

    $response->assertOk()->assertSee('<a data-request-id href="'.route('admin.logs.index', ['request' => 'req_1']).'"', false);
});
