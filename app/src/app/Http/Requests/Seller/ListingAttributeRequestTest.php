<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('reads no attribute field as no selections', function (): void {
    $request = ListingAttributeRequest::create('/whatever', 'PUT');

    expect($request->selections())->toBe([]);
});
