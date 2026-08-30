<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

it('answers 400 for an unrecognised filter value', function (string $query): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/logs?{$query}");

    $response->assertStatus(400);
})->with([
    'domain' => ['domain=storefront'],
    'level' => ['level=critical'],
    'phase' => ['phase=started'],
    'event' => ['event=not.a.real.event'],
    'request too long' => ['request='.str_repeat('a', 65)],
    'request bad characters' => ['request='.rawurlencode('has a space')],
    'txn not a ulid' => ['txn=txn_not-a-ulid'],
    'txn wrong prefix' => ['txn=ord_01J5X3M9A2K8YB7Q4R6T1V0WZE'],
    'txn not even a string' => ['txn[]=txn_01J5X3M9A2K8YB7Q4R6T1V0WZE'],
    'session wrong prefix' => ['session=cus_01J5X3M9A2K8YB7Q4R6T1V0WZE'],
    'actor wrong prefix' => ['actor=ord_01J5X3M9A2K8YB7Q4R6T1V0WZE'],
    'from not an instant' => ['from=yesterday'],
    'to not an instant' => ['to=2026-08-24'],
    'key too many segments' => ['key=a.b.c.d.e'],
    'key bad characters' => ['key=data.order-id'],
    'value without a key' => ['value=ord_1'],
    'group not 1' => ['group=0'],
    'health not 1' => ['health=yes'],
]);

it('treats an empty filter value as absent', function (string $field): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/logs?{$field}=");

    $response->assertOk();
})->with(['domain', 'level', 'phase', 'event', 'request', 'txn', 'session', 'actor', 'msg', 'from', 'to', 'key', 'value', 'group', 'health']);

it('accepts every well-formed filter value', function (string $query): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/logs?{$query}");

    $response->assertOk();
})->with([
    'domain' => ['domain=shop'],
    'level' => ['level=warn'],
    'phase' => ['phase=refused'],
    'event' => ['event=order.place'],
    'request' => ['request=req_1'],
    'txn' => ['txn=txn_01J5X3M9A2K8YB7Q4R6T1V0WZE'],
    'session' => ['session=ses_01J5X3M9A2K8YB7Q4R6T1V0WZE'],
    'actor admin' => ['actor=adm_01J5X3M9A2K8YB7Q4R6T1V0WZE'],
    'actor seller' => ['actor=sel_01J5X3M9A2K8YB7Q4R6T1V0WZE'],
    'actor customer' => ['actor=cus_01J5X3M9A2K8YB7Q4R6T1V0WZE'],
    'msg' => ['msg=placed'],
    'from without fraction' => ['from=2026-08-24T00:00:00Z'],
    'from with fraction' => ['from=2026-08-24T00:00:00.123Z'],
    'to' => ['to=2026-08-25T00:00:00Z'],
    'key one segment' => ['key=event'],
    'key four segments' => ['key=data.a.b.c'],
    'key and value' => ['key=data.order_id&value=ord_1'],
    'group' => ['group=1'],
    'health' => ['health=1'],
]);

it('sends a guest to the admin login page before any filter is validated', function (): void {
    $response = $this->get('/admin/logs?level=bogus');

    $response->assertRedirect(route('auth.admin.login'));
});
