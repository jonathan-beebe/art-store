<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Analytics\Analytics;
use Illuminate\Support\Facades\DB;

it('records help.answered with the article slug as subject, the seller in data, and thanks the seller in place', function (): void {
    $seller = $this->seller('Weasley Studio');

    $response = $this->actingAs($seller, 'seller')->post('/seller/support/articles/printing-a-label-from-an-order/answered');
    app(Analytics::class)->flush();

    $response->assertRedirect(route('seller.support.articles.show', 'printing-a-label-from-an-order'));
    $response->assertSessionHas('status', 'Thanks — glad it helped.');

    $row = DB::connection('analytics')->table('analytics_events')->where('name', 'help.answered')->sole();
    /** @var string $data */
    $data = $row->data;

    expect($row->subject_type)->toBe('help_article')
        ->and($row->subject_id)->toBe('printing-a-label-from-an-order')
        ->and($row->actor_id)->toBeNull()
        ->and(json_decode($data, true))->toMatchArray(['seller_id' => $seller->id]);
});

it('shows the thanks banner on the article page the redirect lands on', function (): void {
    $seller = $this->seller();

    $this->actingAs($seller, 'seller')->post('/seller/support/articles/printing-a-label-from-an-order/answered');
    $response = $this->actingAs($seller, 'seller')->get('/seller/support/articles/printing-a-label-from-an-order');

    $response->assertOk()->assertSee('Thanks — glad it helped.');
});

it('records nothing twice for the same seller and article within a day', function (): void {
    $seller = $this->seller();
    $analytics = app(Analytics::class);

    $this->travelTo($this->moment('2026-09-03 09:00:00'));
    $this->actingAs($seller, 'seller')->post('/seller/support/articles/printing-a-label-from-an-order/answered');

    $this->travelTo($this->moment('2026-09-03 21:00:00'));
    $this->actingAs($seller, 'seller')->post('/seller/support/articles/printing-a-label-from-an-order/answered');
    $analytics->flush();

    expect(DB::connection('analytics')->table('analytics_events')->where('name', 'help.answered')->count())->toBe(1);
});

it('records again the next UTC day', function (): void {
    $seller = $this->seller();
    $analytics = app(Analytics::class);

    $this->travelTo($this->moment('2026-09-03 09:00:00'));
    $this->actingAs($seller, 'seller')->post('/seller/support/articles/printing-a-label-from-an-order/answered');

    $this->travelTo($this->moment('2026-09-04 09:00:00'));
    $this->actingAs($seller, 'seller')->post('/seller/support/articles/printing-a-label-from-an-order/answered');
    $analytics->flush();

    expect(DB::connection('analytics')->table('analytics_events')->where('name', 'help.answered')->count())->toBe(2);
});

it('records separately for a different seller on the same article and day', function (): void {
    $first = $this->seller('Weasley Studio');
    $second = $this->seller('Ollivanders');
    $analytics = app(Analytics::class);

    $this->actingAs($first, 'seller')->post('/seller/support/articles/printing-a-label-from-an-order/answered');
    $this->actingAs($second, 'seller')->post('/seller/support/articles/printing-a-label-from-an-order/answered');
    $analytics->flush();

    expect(DB::connection('analytics')->table('analytics_events')->where('name', 'help.answered')->count())->toBe(2);
});

it('answers 404 for an unknown article slug on the Yes route', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->post('/seller/support/articles/not-a-real-article/answered');

    $response->assertNotFound();
});

it('records help.unanswered with the article slug as subject and the seller in data, then opens the conversation form', function (): void {
    $seller = $this->seller('Weasley Studio');

    $response = $this->actingAs($seller, 'seller')->post('/seller/support/articles/printing-a-label-from-an-order/unanswered');
    app(Analytics::class)->flush();

    $response->assertRedirect(route('seller.support.create'));

    $row = DB::connection('analytics')->table('analytics_events')->where('name', 'help.unanswered')->sole();
    /** @var string $data */
    $data = $row->data;

    expect($row->subject_type)->toBe('help_article')
        ->and($row->subject_id)->toBe('printing-a-label-from-an-order')
        ->and($row->actor_id)->toBeNull()
        ->and(json_decode($data, true))->toMatchArray(['seller_id' => $seller->id]);
});

it('records nothing twice for the same seller and article within a day on the No route', function (): void {
    $seller = $this->seller();
    $analytics = app(Analytics::class);

    $this->travelTo($this->moment('2026-09-03 09:00:00'));
    $this->actingAs($seller, 'seller')->post('/seller/support/articles/printing-a-label-from-an-order/unanswered');

    $this->travelTo($this->moment('2026-09-03 21:00:00'));
    $this->actingAs($seller, 'seller')->post('/seller/support/articles/printing-a-label-from-an-order/unanswered');
    $analytics->flush();

    expect(DB::connection('analytics')->table('analytics_events')->where('name', 'help.unanswered')->count())->toBe(1);
});

it('does not collide with a "yes" recorded for the same seller and article the same day', function (): void {
    $seller = $this->seller();
    $analytics = app(Analytics::class);

    $this->actingAs($seller, 'seller')->post('/seller/support/articles/printing-a-label-from-an-order/answered');
    $this->actingAs($seller, 'seller')->post('/seller/support/articles/printing-a-label-from-an-order/unanswered');
    $analytics->flush();

    expect(DB::connection('analytics')->table('analytics_events')->whereIn('name', ['help.answered', 'help.unanswered'])->count())->toBe(2);
});

it('answers 404 for an unknown article slug on the No route', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->post('/seller/support/articles/not-a-real-article/unanswered');

    $response->assertNotFound();
});

it('leaves /admin/analytics/actors unchanged by a seller\'s help click', function (): void {
    $seller = $this->seller();
    $analytics = app(Analytics::class);

    $this->actingAs($seller, 'seller')->post('/seller/support/articles/printing-a-label-from-an-order/answered');
    $analytics->flush();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors');

    $response->assertOk();
    $response->assertDontSee($seller->id);
});
