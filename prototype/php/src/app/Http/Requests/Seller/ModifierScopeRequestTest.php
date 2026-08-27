<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('reads an empty selection as no option values', function (): void {
    $request = ModifierScopeRequest::create('/whatever', 'POST');

    expect($request->optionValues())->toBe([]);
});
