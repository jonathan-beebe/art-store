<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

it('carries a form body without the framework fields or the card fields', function (): void {
    $request = Request::create('/checkout', 'POST', [
        '_token' => 'csrf',
        '_method' => 'PUT',
        'name' => 'Harry Potter',
        'card_number' => '4242424242424242',
        'card_expiry' => '12/30',
        'card_cvc' => '123',
        'address' => ['line1' => '4 Privet Drive', 'city' => 'Little Whinging'],
    ]);

    expect(LoggedRequestBody::of($request))->toBe([
        'name' => 'Harry Potter',
        'address' => ['line1' => '4 Privet Drive', 'city' => 'Little Whinging'],
    ]);
});

it('redacts a value shaped like an email and caps a long value', function (): void {
    $request = Request::create('/support', 'POST', ['email' => 'harry@hogwarts.example', 'body' => str_repeat('a', 600)]);

    $body = LoggedRequestBody::of($request);

    expect($body['email'] ?? null)->toBe('[redacted]')
        ->and($body['body'] ?? null)->toBe(str_repeat('a', 500).'…');
});

it('reads a JSON body the same way', function (): void {
    $request = Request::create('/api', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"name":"cup","tags":["blue","tall"]}');

    expect(LoggedRequestBody::of($request))->toBe(['name' => 'cup', 'tags' => ['blue', 'tall']]);
});

it('reduces an upload to its name and size', function (): void {
    $file = UploadedFile::fake()->create('cup.jpg', 12);
    $request = Request::create('/seller/listings/lst_1/images', 'POST', ['alt' => 'a cup'], files: ['image' => $file]);

    expect(LoggedRequestBody::of($request))->toBe([
        'alt' => 'a cup',
        'image' => ['file' => 'cup.jpg', 'bytes' => 12 * 1024],
    ]);
});

it('carries nothing for a read, an empty body, or the MCP endpoint', function (): void {
    expect(LoggedRequestBody::of(Request::create('/', 'GET', ['q' => 'cup'])))->toBeNull()
        ->and(LoggedRequestBody::of(Request::create('/checkout', 'POST', ['_token' => 'csrf'])))->toBeNull()
        ->and(LoggedRequestBody::of(Request::create('/mcp', 'POST', ['jsonrpc' => '2.0', 'method' => 'ping'])))->toBeNull();
});
