<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('names the actor-kind segmented control\'s three values', function (): void {
    expect(array_column(ActorKindFilter::cases(), 'value'))->toBe(['all', 'anonymous', 'verified']);
});

it('admits an actor kind', function (ActorKindFilter $filter, ActorKind $kind, bool $admits): void {
    expect($filter->admits($kind))->toBe($admits);
})->with([
    'all admits verified' => [ActorKindFilter::All, ActorKind::Verified, true],
    'all admits anonymous' => [ActorKindFilter::All, ActorKind::Anonymous, true],
    'verified admits verified' => [ActorKindFilter::Verified, ActorKind::Verified, true],
    'verified rejects anonymous' => [ActorKindFilter::Verified, ActorKind::Anonymous, false],
    'anonymous admits anonymous' => [ActorKindFilter::Anonymous, ActorKind::Anonymous, true],
    'anonymous rejects verified' => [ActorKindFilter::Anonymous, ActorKind::Verified, false],
]);
