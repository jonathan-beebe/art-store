<?php

declare(strict_types=1);

namespace App\View\Components\Seller;

use Illuminate\Support\Facades\Blade;

it('renders the default coming-pill copy in the muted pill classes', function (): void {
    $html = Blade::render('<x-seller.coming-pill />');

    expect($html)->toContain('rounded-full border border-gray-200 dark:border-gray-700 px-2 py-0.5 text-xs')
        ->toContain('coming — not in this version');
});

it('renders the given text instead of the default', function (): void {
    $html = Blade::render('<x-seller.coming-pill text="not yet" />');

    expect($html)->toContain('>not yet<');
    expect($html)->not->toContain('coming — not in this version');
});
