<?php

declare(strict_types=1);

namespace App\Logging\Admin;

use App\Logging\LogDomain;
use Tests\LogViewerFixtures as Fixtures;

it('counts and pages rows newest first, tiebreaking on id within one ts', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['ts' => '2026-08-24T12:00:00.000Z', 'msg' => 'first']),
        Fixtures::line(['ts' => '2026-08-24T12:00:00.001Z', 'msg' => 'second']),
        Fixtures::line(['ts' => '2026-08-24T12:00:00.001Z', 'msg' => 'third']),
    ]);

    expect($query->count(new LogRowFilters))->toBe(3);

    $page1 = $query->rows(new LogRowFilters, limit: 2, offset: 0);
    expect($page1)->toHaveCount(2)
        ->and($page1[0]->msg)->toBe('third')
        ->and($page1[1]->msg)->toBe('second');

    $page2 = $query->rows(new LogRowFilters, limit: 2, offset: 2);
    expect($page2)->toHaveCount(1)->and($page2[0]->msg)->toBe('first');
});

it('filters on each mirrored column equality', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['level' => 'warn', 'phase' => 'refused', 'event' => 'order.pay', 'request_id' => 'req_1', 'txn_id' => 'txn_1', 'session_id' => 'ses_1', 'actor_id' => 'cus_1', 'msg' => 'target']),
        Fixtures::line(['level' => 'info', 'phase' => 'did', 'event' => 'order.place', 'request_id' => 'req_2', 'txn_id' => 'txn_2', 'session_id' => 'ses_2', 'actor_id' => 'cus_2', 'msg' => 'other']),
    ]);

    foreach ([
        new LogRowFilters(level: 'warn'),
        new LogRowFilters(phase: 'refused'),
        new LogRowFilters(event: 'order.pay'),
        new LogRowFilters(requestId: 'req_1'),
        new LogRowFilters(txnId: 'txn_1'),
        new LogRowFilters(sessionId: 'ses_1'),
        new LogRowFilters(actorId: 'cus_1'),
    ] as $filters) {
        $rows = $query->rows($filters, 50, 0);
        expect($rows)->toHaveCount(1)->and($rows[0]->msg)->toBe('target');
    }
});

it('derives each lines domain from its own requests opening line path', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['request_id' => 'req_admin_root', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /admin/orders', 'data' => ['method' => 'GET', 'path' => '/admin/orders']]),
        Fixtures::line(['request_id' => 'req_admin_root', 'msg' => 'admin body line']),
        Fixtures::line(['request_id' => 'req_admin_root2', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /admin', 'data' => ['method' => 'GET', 'path' => '/admin']]),
        Fixtures::line(['request_id' => 'req_seller_root', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /seller/listings', 'data' => ['method' => 'GET', 'path' => '/seller/listings']]),
        Fixtures::line(['request_id' => 'req_seller_root2', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /seller', 'data' => ['method' => 'GET', 'path' => '/seller']]),
        Fixtures::line(['request_id' => 'req_shop_root', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /checkout', 'data' => ['method' => 'GET', 'path' => '/checkout']]),
        Fixtures::line(['request_id' => 'req_health_root', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /up', 'data' => ['method' => 'GET', 'path' => '/up']]),
        Fixtures::line(['request_id' => 'req_events_root', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /events', 'data' => ['method' => 'GET', 'path' => '/events']]),
        Fixtures::line(['request_id' => null, 'msg' => 'orphan, no request id']),
    ]);

    $msgsFor = fn (LogDomain $domain): array => array_map(
        fn (LogRow $row): ?string => $row->msg,
        $query->rows(new LogRowFilters(domain: $domain, hideHealth: false), 50, 0),
    );

    expect($msgsFor(LogDomain::Admin))->toEqualCanonicalizing(['GET /admin/orders', 'admin body line', 'GET /admin']);
    expect($msgsFor(LogDomain::Seller))->toEqualCanonicalizing(['GET /seller/listings', 'GET /seller']);
    expect($msgsFor(LogDomain::Shop))->toEqualCanonicalizing(['GET /checkout']);
});

it('hides a health-check requests lines by default, shows them with health=1, and composes with domain', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['request_id' => 'req_health', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /up', 'data' => ['method' => 'GET', 'path' => '/up']]),
        Fixtures::line(['request_id' => 'req_health', 'event' => 'http.request', 'phase' => 'did', 'msg' => 'GET /up 200', 'data' => ['status' => 200]]),
        Fixtures::line(['request_id' => 'req_shop', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /checkout', 'data' => ['method' => 'GET', 'path' => '/checkout']]),
        Fixtures::line(['request_id' => null, 'msg' => 'boot line, no request id']),
    ]);

    expect($query->count(new LogRowFilters))->toBe(2)
        ->and($query->count(new LogRowFilters(hideHealth: false)))->toBe(4)
        ->and($query->count(new LogRowFilters(domain: LogDomain::Shop)))->toBe(1)
        ->and($query->count(new LogRowFilters(domain: LogDomain::Shop, hideHealth: false)))->toBe(1);
});

it('hides the log viewers own requests by default, shows them with viewer=1, and composes with domain', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['request_id' => 'req_viewer', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /admin/logs', 'data' => ['method' => 'GET', 'path' => '/admin/logs']]),
        Fixtures::line(['request_id' => 'req_viewer', 'event' => 'http.request', 'phase' => 'did', 'msg' => 'GET /admin/logs 200', 'data' => ['status' => 200]]),
        Fixtures::line(['request_id' => 'req_viewer_story', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /admin/logs/requests/req_1', 'data' => ['method' => 'GET', 'path' => '/admin/logs/requests/req_1']]),
        Fixtures::line(['request_id' => 'req_admin', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /admin/orders', 'data' => ['method' => 'GET', 'path' => '/admin/orders']]),
        Fixtures::line(['request_id' => null, 'msg' => 'boot line, no request id']),
    ]);

    expect($query->count(new LogRowFilters))->toBe(2)
        ->and($query->count(new LogRowFilters(hideViewer: false)))->toBe(5)
        ->and($query->count(new LogRowFilters(domain: LogDomain::Admin)))->toBe(1)
        ->and($query->count(new LogRowFilters(domain: LogDomain::Admin, hideViewer: false)))->toBe(4);
});

it('hides log viewer requests at a path segment boundary, not a bare prefix match', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['request_id' => 'req_exact', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /admin/logs', 'data' => ['method' => 'GET', 'path' => '/admin/logs']]),
        Fixtures::line(['request_id' => 'req_nested', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /admin/logs/requests/req_1', 'data' => ['method' => 'GET', 'path' => '/admin/logs/requests/req_1']]),
        Fixtures::line(['request_id' => 'req_lookalike', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /admin/logs-export', 'data' => ['method' => 'GET', 'path' => '/admin/logs-export']]),
    ]);

    $msgs = array_map(fn (LogRow $row): ?string => $row->msg, $query->rows(new LogRowFilters, 50, 0));

    expect($msgs)->toBe(['GET /admin/logs-export']);
});

it('matches msg as a literal substring, not treating % or _ as wildcards', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['msg' => 'gave a 50% discount']),
        Fixtures::line(['msg' => 'gave a 5099 discount']),
    ]);

    $rows = $query->rows(new LogRowFilters(msg: '50%'), 50, 0);

    expect($rows)->toHaveCount(1)->and($rows[0]->msg)->toBe('gave a 50% discount');
});

it('filters from/to lexically against ts', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['ts' => '2026-08-24T00:00:00.000Z', 'msg' => 'too early']),
        Fixtures::line(['ts' => '2026-08-24T12:00:00.000Z', 'msg' => 'in range']),
        Fixtures::line(['ts' => '2026-08-25T00:00:00.000Z', 'msg' => 'too late']),
    ]);

    $rows = $query->rows(new LogRowFilters(from: '2026-08-24T06:00:00.000Z', to: '2026-08-24T18:00:00.000Z'), 50, 0);

    expect($rows)->toHaveCount(1)->and($rows[0]->msg)->toBe('in range');
});

it('short-circuits a mirrored-column key to that columns equality', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['event' => 'order.pay', 'msg' => 'target']),
        Fixtures::line(['event' => 'order.place', 'msg' => 'other']),
    ]);

    $rows = $query->rows(new LogRowFilters(key: 'event', value: 'order.pay'), 50, 0);

    expect($rows)->toHaveCount(1)->and($rows[0]->msg)->toBe('target');
});

it('filters on a dotted JSON path into data', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['msg' => 'target', 'data' => ['order_id' => 'ord_1']]),
        Fixtures::line(['msg' => 'other', 'data' => ['order_id' => 'ord_2']]),
    ]);

    $rows = $query->rows(new LogRowFilters(key: 'data.order_id', value: 'ord_1'), 50, 0);

    expect($rows)->toHaveCount(1)->and($rows[0]->msg)->toBe('target');
});

it('matches a numeric-looking value against both the JSON number and its string form', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['msg' => 'numeric', 'data' => ['amount_cents' => 1200]]),
        Fixtures::line(['msg' => 'stringy', 'data' => ['amount_cents' => '1200']]),
        Fixtures::line(['msg' => 'unrelated', 'data' => ['amount_cents' => 500]]),
    ]);

    $rows = $query->rows(new LogRowFilters(key: 'data.amount_cents', value: '1200'), 50, 0);

    expect($rows)->toHaveCount(2)
        ->and(array_map(fn (LogRow $row): ?string => $row->msg, $rows))->toEqualCanonicalizing(['numeric', 'stringy']);
});

it('treats a key with no value as an existence filter', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['msg' => 'has refund', 'data' => ['refund_id' => 'rfd_1']]),
        Fixtures::line(['msg' => 'no refund', 'data' => ['order_id' => 'ord_1']]),
        Fixtures::line(['msg' => 'no data at all']),
    ]);

    $rows = $query->rows(new LogRowFilters(key: 'data.refund_id'), 50, 0);

    expect($rows)->toHaveCount(1)->and($rows[0]->msg)->toBe('has refund');
});

it('tallies every level under the current filters minus level itself, zero included', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['level' => 'error', 'phase' => 'failed', 'event' => 'order.pay']),
        Fixtures::line(['level' => 'warn', 'event' => 'rate_limit.exceed']),
        Fixtures::line(['level' => 'warn', 'event' => 'order.pay']),
        Fixtures::line(['level' => 'info', 'event' => 'order.pay']),
    ]);

    $tallies = $query->levelTallies(new LogRowFilters(level: 'error', event: 'order.pay'));

    expect($tallies)->toBe(['debug' => 0, 'info' => 1, 'warn' => 1, 'error' => 1]);
});

it('returns one requests lines in ts then id order, and its total count', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['request_id' => 'req_1', 'ts' => '2026-08-24T12:00:00.002Z', 'msg' => 'second']),
        Fixtures::line(['request_id' => 'req_1', 'ts' => '2026-08-24T12:00:00.001Z', 'msg' => 'first']),
        Fixtures::line(['request_id' => 'req_2', 'ts' => '2026-08-24T12:00:00.000Z', 'msg' => 'other request']),
    ]);

    $rows = $query->storyRows('req_1');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->msg)->toBe('first')
        ->and($rows[1]->msg)->toBe('second')
        ->and($query->storyCount('req_1'))->toBe(2);
});

it('groups by request, summarizing the root http.request will/did pair, newest activity first', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['request_id' => 'req_old', 'ts' => '2026-08-24T09:00:00.000Z', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'GET /checkout', 'data' => ['method' => 'GET', 'path' => '/checkout']]),
        Fixtures::line(['request_id' => 'req_old', 'ts' => '2026-08-24T09:00:00.010Z', 'event' => 'http.request', 'phase' => 'did', 'msg' => 'GET /checkout 200', 'level' => 'info', 'duration_ms' => 10, 'data' => ['status' => 200]]),
        Fixtures::line(['request_id' => 'req_new', 'ts' => '2026-08-24T10:00:00.000Z', 'event' => 'http.request', 'phase' => 'will', 'msg' => 'POST /checkout', 'data' => ['method' => 'POST', 'path' => '/checkout']]),
        Fixtures::line(['request_id' => 'req_new', 'ts' => '2026-08-24T10:00:00.020Z', 'event' => 'http.request', 'phase' => 'failed', 'msg' => 'POST /checkout broke', 'level' => 'error', 'duration_ms' => 20, 'data' => ['status' => 500]]),
        Fixtures::line(['ts' => '2026-08-24T11:00:00.000Z', 'request_id' => null, 'msg' => 'orphan line']),
    ]);

    expect($query->countGroups(new LogRowFilters))->toBe(3);

    $groups = $query->groups(new LogRowFilters, limit: 50, offset: 0);

    expect($groups)->toHaveCount(3);

    expect($groups[0]->kind)->toBe('line')
        ->and($groups[0]->msg)->toBe('orphan line')
        ->and($groups[0]->lineCount)->toBe(1);

    expect($groups[1]->key)->toBe('req_new')
        ->and($groups[1]->kind)->toBe('request')
        ->and($groups[1]->method)->toBe('POST')
        ->and($groups[1]->path)->toBe('/checkout')
        ->and($groups[1]->status)->toBe(500)
        ->and($groups[1]->durationMs)->toBe(20)
        ->and($groups[1]->level)->toBe('error')
        ->and($groups[1]->msg)->toBe('POST /checkout broke')
        ->and($groups[1]->lineCount)->toBe(2);

    expect($groups[2]->key)->toBe('req_old')
        ->and($groups[2]->status)->toBe(200)
        ->and($groups[2]->durationMs)->toBe(10);
});

it('summarizes a request that never opened or closed with an http.request pair', function (): void {
    $query = Fixtures::query([Fixtures::line(['request_id' => 'req_1', 'msg' => 'some action', 'event' => 'order.place'])]);

    $group = $query->groups(new LogRowFilters, limit: 50, offset: 0)[0];

    expect($group->kind)->toBe('request')
        ->and($group->method)->toBeNull()
        ->and($group->path)->toBeNull()
        ->and($group->status)->toBeNull()
        ->and($group->durationMs)->toBeNull()
        ->and($group->msg)->toBeNull();
});

it('pages groups newest activity first', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['request_id' => 'req_a', 'ts' => '2026-08-24T09:00:00.000Z']),
        Fixtures::line(['request_id' => 'req_b', 'ts' => '2026-08-24T10:00:00.000Z']),
        Fixtures::line(['request_id' => 'req_c', 'ts' => '2026-08-24T11:00:00.000Z']),
    ]);

    $page1 = $query->groups(new LogRowFilters, limit: 2, offset: 0);
    $page2 = $query->groups(new LogRowFilters, limit: 2, offset: 2);

    expect(array_map(fn (LogRequestGroup $group): string => $group->key, $page1))->toBe(['req_c', 'req_b'])
        ->and(array_map(fn (LogRequestGroup $group): string => $group->key, $page2))->toBe(['req_a']);
});

it('tiebreaks groups with the same last activity by key, descending', function (): void {
    $query = Fixtures::query([
        Fixtures::line(['request_id' => 'req_a', 'ts' => '2026-08-24T09:00:00.000Z']),
        Fixtures::line(['request_id' => 'req_b', 'ts' => '2026-08-24T09:00:00.000Z']),
    ]);

    $groups = $query->groups(new LogRowFilters, limit: 50, offset: 0);

    expect(array_map(fn (LogRequestGroup $group): string => $group->key, $groups))->toBe(['req_b', 'req_a']);
});

it('returns no groups past the end of the filtered set', function (): void {
    $query = Fixtures::query([Fixtures::line()]);

    expect($query->groups(new LogRowFilters, limit: 50, offset: 10))->toBe([]);
});
