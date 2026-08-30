<?php

declare(strict_types=1);

namespace App\Logging\Admin;

function prefixedTestId(string $prefix): string
{
    return "{$prefix}_01J5X3M9A2K8YB7Q4R6T1V0WZE";
}

it('links a prefixed id to its admin detail page', function (string $prefix, string $routeName): void {
    $id = prefixedTestId($prefix);

    expect(LogIdLinks::hrefFor($id))->toBe(route($routeName, [$id]));
})->with([
    'order' => ['ord', 'admin.orders.show'],
    'customer' => ['cus', 'admin.customers.show'],
    'seller' => ['sel', 'admin.sellers.show'],
    'listing' => ['lst', 'admin.listings.show'],
    'fulfillment' => ['ful', 'admin.fulfillments.show'],
    'conversation' => ['cnv', 'admin.messages.show'],
]);

it('links a transaction id back into the log list as a filter', function (): void {
    $id = prefixedTestId('txn');

    expect(LogIdLinks::hrefFor($id))->toBe(route('admin.logs.index', ['txn' => $id]));
});

it('links a session id back into the log list as a filter', function (): void {
    $id = prefixedTestId('ses');

    expect(LogIdLinks::hrefFor($id))->toBe(route('admin.logs.index', ['session' => $id]));
});

it('renders a message id plain — messages have no detail page', function (): void {
    expect(LogIdLinks::hrefFor(prefixedTestId('msg')))->toBeNull();
});

it('renders an id from a table with no admin page plain', function (): void {
    expect(LogIdLinks::hrefFor(prefixedTestId('obx')))->toBeNull();
});

it('wraps every linkable id in the text with an anchor and escapes the rest', function (): void {
    $orderId = prefixedTestId('ord');
    $messageId = prefixedTestId('msg');
    $text = "<order {$orderId}> and <msg {$messageId}>";

    $html = LogIdLinks::linkify($text);

    expect($html)->toBe(
        '&lt;order <a href="'.e(route('admin.orders.show', [$orderId])).'" class="underline">'.$orderId.'</a>'
        .'&gt; and &lt;msg '.$messageId.'&gt;',
    );
});

it('escapes text with no linkable id at all', function (): void {
    expect(LogIdLinks::linkify('<no ids here>'))->toBe('&lt;no ids here&gt;');
});
