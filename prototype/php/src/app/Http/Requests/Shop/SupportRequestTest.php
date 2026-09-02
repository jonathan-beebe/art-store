<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

it('reads the subject and body the visitor typed', function (): void {
    $request = SupportRequest::create('/support', 'POST', [
        'subject' => 'Where is my order?',
        'body' => 'The tracking has not moved since Tuesday.',
    ]);

    expect($request->title()->value)->toBe('Where is my order?')
        ->and($request->body()->value)->toBe('The tracking has not moved since Tuesday.');
});
