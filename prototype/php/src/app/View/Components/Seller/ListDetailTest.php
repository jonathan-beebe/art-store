<?php

declare(strict_types=1);

namespace App\View\Components\Seller;

use Illuminate\Support\Facades\Blade;

it('renders the list header, list body, and detail slot', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-seller.list-detail>
            <x-slot:listHeader>Orders</x-slot:listHeader>
            <x-slot:list>Neville Longbottom</x-slot:list>
            Order detail
        </x-seller.list-detail>
        BLADE);

    expect($html)
        ->toContain('Orders')
        ->toContain('Neville Longbottom')
        ->toContain('Order detail');
});

it('shows the list and hides the detail below lg by default', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-seller.list-detail>
            <x-slot:list>the list</x-slot:list>
            the detail
        </x-seller.list-detail>
        BLADE);

    expect($html)->toContain('hidden lg:block');
    expect($html)->not->toContain('hidden lg:flex');
});

it('shows the detail and hides the list below lg when mobile is detail', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-seller.list-detail mobile="detail">
            <x-slot:list>the list</x-slot:list>
            the detail
        </x-seller.list-detail>
        BLADE);

    expect($html)->toContain('hidden lg:flex');
    expect($html)->not->toContain('hidden lg:block');
});
