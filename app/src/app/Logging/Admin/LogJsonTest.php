<?php

declare(strict_types=1);

namespace App\Logging\Admin;

it('pretty-prints stored JSON text', function (): void {
    expect(LogJson::pretty('{"order_id":"ord_1","amount_cents":1200}'))
        ->toBe("{\n    \"order_id\": \"ord_1\",\n    \"amount_cents\": 1200\n}");
});

it('renders unparsable text as it stands', function (): void {
    expect(LogJson::pretty('not json'))->toBe('not json');
});
