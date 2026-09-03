<?php

declare(strict_types=1);

namespace App\Support\Messaging;

it('reads whether a type or a status is part of the current selection', function (): void {
    $query = new InboxQuery('all', ['questions', 'orders'], ['open']);

    expect($query->hasType('questions'))->toBeTrue()
        ->and($query->hasType('support'))->toBeFalse()
        ->and($query->hasStatus('open'))->toBeTrue()
        ->and($query->hasStatus('resolved'))->toBeFalse();
});

it('carries the full selection as route params', function (): void {
    $query = new InboxQuery('buyers', ['questions'], ['open', 'resolved']);

    expect($query->toRouteParams())->toBe([
        'domain' => 'buyers',
        'type' => ['questions'],
        'status' => ['open', 'resolved'],
    ]);
});

it('resets to the current domain alone', function (): void {
    $query = new InboxQuery('support', ['questions'], ['resolved']);

    expect($query->resetRouteParams())->toBe(['domain' => 'support']);
});

it('counts zero changes at the default selection', function (): void {
    $query = new InboxQuery('all', ['questions', 'orders', 'support'], ['open']);

    expect($query->changesFromDefault(['questions', 'orders', 'support'], ['open']))->toBe(0);
});

it('counts one change per unchecked type', function (): void {
    $query = new InboxQuery('all', ['questions'], ['open']);

    expect($query->changesFromDefault(['questions', 'orders', 'support'], ['open']))->toBe(2);
});

it('counts one change for a status added on top of the default', function (): void {
    $addedResolved = new InboxQuery('all', ['questions', 'orders', 'support'], ['open', 'resolved']);

    expect($addedResolved->changesFromDefault(['questions', 'orders', 'support'], ['open']))->toBe(1);
});

it('counts two changes for a status swapped for the default, one dropped and one added', function (): void {
    $swapped = new InboxQuery('all', ['questions', 'orders', 'support'], ['resolved']);

    expect($swapped->changesFromDefault(['questions', 'orders', 'support'], ['open']))->toBe(2);
});

it('intersects two kind lists to their overlap', function (): void {
    expect(InboxQuery::intersectKinds(['a', 'b'], ['b', 'c']))->toBe(['b'])
        ->and(InboxQuery::intersectKinds(['a', 'b'], ['c']))->toBe([])
        ->and(InboxQuery::intersectKinds(null, ['a']))->toBe(['a'])
        ->and(InboxQuery::intersectKinds(['a'], null))->toBe(['a'])
        // @phpstan-ignore argument.templateType (neither side gives T anything to infer from)
        ->and(InboxQuery::intersectKinds(null, null))->toBeNull();
});
