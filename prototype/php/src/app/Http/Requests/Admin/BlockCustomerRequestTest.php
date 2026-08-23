<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

it('reads the submitted reason', function (): void {
    $request = BlockCustomerRequest::create('/admin/customers/1/blocks', 'POST', ['reason' => 'Chargeback fraud.']);

    expect($request->reason())->toBe('Chargeback fraud.');
});
