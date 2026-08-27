<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('carries no validation rules of its own', function (): void {
    expect((new GenerateVariantsRequest)->rules())->toBe([]);
});
