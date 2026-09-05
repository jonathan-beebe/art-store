<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

it('reads save as the default op when the form names none', function (): void {
    $request = FunnelRequest::create('/admin/funnels', 'POST', ['name' => 'Storefront']);

    expect($request->op())->toBe('save')
        ->and($request->isSave())->toBeTrue();
});

it('reads the pressed button\'s op', function (): void {
    $request = FunnelRequest::create('/admin/funnels', 'POST', ['op' => 'add_step']);

    expect($request->op())->toBe('add_step')
        ->and($request->isSave())->toBeFalse();
});

it('derives the slug from the name when the slug field is blank', function (): void {
    $request = FunnelRequest::create('/admin/funnels', 'POST', ['name' => 'Gift Shopping']);

    expect($request->slug())->toBe('gift-shopping');
});

it('keeps a submitted slug rather than deriving one', function (): void {
    $request = FunnelRequest::create('/admin/funnels', 'POST', ['name' => 'Gift Shopping', 'slug' => 'gifts']);

    expect($request->slug())->toBe('gifts');
});

it('reads the submitted step names in order', function (): void {
    $request = FunnelRequest::create('/admin/funnels', 'POST', ['steps' => ['listing.view', 'listing.cart_add']]);

    expect($request->stepNames())->toBe(['listing.view', 'listing.cart_add']);
});

it('reads no steps field as an empty list', function (): void {
    $request = FunnelRequest::create('/admin/funnels', 'POST', []);

    expect($request->stepNames())->toBe([]);
});

it('builds a FunnelDefinition from the submitted steps', function (): void {
    $request = FunnelRequest::create('/admin/funnels', 'POST', ['steps' => ['listing.view', 'listing.cart_add']]);

    expect($request->definition()->steps)->toHaveCount(2);
});
