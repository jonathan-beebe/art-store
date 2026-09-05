<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

function attentionRow(string $title = 'Molly Weasley'): AttentionRow
{
    return new AttentionRow('MW', $title, 'A tea bowl', '2 days ago', '/seller/orders');
}

function attentionLinks(): AttentionLinks
{
    return new AttentionLinks('/seller/orders', '/seller/messages', '/seller/earnings', '/seller/listings');
}

/**
 * @return list<AttentionGroup>
 */
function attentionGroups(
    ?AttentionRows $toShip = null,
    ?AttentionRows $waiting = null,
    ?AttentionRows $payout = null,
    ?AttentionRows $listings = null,
): array {
    return AttentionQueue::build(
        toShip: $toShip ?? AttentionRows::of([]),
        waiting: $waiting ?? AttentionRows::of([]),
        payout: $payout ?? AttentionRows::of([]),
        listings: $listings ?? AttentionRows::of([]),
        payoutDate: new DateTimeImmutable('2026-09-07'),
        links: attentionLinks(),
    );
}

function attentionRowsOf(int $count): AttentionRows
{
    $rows = [];

    for ($i = 0; $i < $count; $i++) {
        $rows[] = attentionRow();
    }

    return AttentionRows::of($rows);
}

it('builds the four groups, in the order the dashboard renders them', function (): void {
    $groups = attentionGroups();

    expect($groups)->toHaveCount(4)
        ->and(array_map(fn (AttentionGroup $group): string => $group->actionLabel, $groups))
        ->toBe(['Open orders', 'Open messages', 'See earnings', 'Open listings']);
});

it('gives each group the link to the tool that clears it', function (): void {
    $groups = attentionGroups();

    expect(array_map(fn (AttentionGroup $group): string => $group->actionHref, $groups))
        ->toBe(['/seller/orders', '/seller/messages', '/seller/earnings', '/seller/listings']);
});

it('counts the orders waiting to ship in the heading', function (int $rows, string $title): void {
    $groups = attentionGroups(toShip: attentionRowsOf($rows));

    expect($groups[0]->title)->toBe($title);
})->with([
    'none' => [0, 'No orders to ship'],
    'one' => [1, '1 order to ship'],
    'three' => [3, '3 orders to ship'],
]);

it('counts the messages waiting on the seller in the heading', function (int $rows, string $title): void {
    $groups = attentionGroups(waiting: attentionRowsOf($rows));

    expect($groups[1]->title)->toBe($title);
})->with([
    'none' => [0, 'No messages waiting on you'],
    'one' => [1, '1 message waiting on you'],
    'four' => [4, '4 messages waiting on you'],
]);

it('counts the listings that need work in the heading', function (int $rows, string $title): void {
    $groups = attentionGroups(listings: attentionRowsOf($rows));

    expect($groups[3]->title)->toBe($title);
})->with([
    'none' => [0, 'No listings need work'],
    'one' => [1, '1 listing needs work'],
    'two' => [2, '2 listings need work'],
]);

it('names the payout day in the payout heading', function (): void {
    $groups = attentionGroups();

    expect($groups[2]->title)->toBe('Payout Monday, Sep 7');
});

it('gives an empty group a sentence to show in place of its rows', function (int $index, string $sentence): void {
    $groups = attentionGroups();

    expect($groups[$index]->isEmpty())->toBeTrue()
        ->and($groups[$index]->emptySentence)->toBe($sentence);
})->with([
    'orders' => [0, 'Nothing is waiting to ship.'],
    'messages' => [1, 'Every buyer has heard back from you.'],
    'payout' => [2, 'Nothing has settled yet.'],
    'listings' => [3, 'Every listing is published and in stock.'],
]);

it('hands each group its rows in the order it was given them', function (): void {
    $first = attentionRow('Ginny Weasley');
    $second = attentionRow('Luna Lovegood');

    $groups = attentionGroups(toShip: AttentionRows::of([$first, $second]));

    expect($groups[0]->rows)->toBe([$first, $second])
        ->and($groups[0]->isEmpty())->toBeFalse();
});

it('reads a parcel as overdue once it has waited past two days', function (string $placedAt, bool $overdue): void {
    expect(AttentionQueue::isOverdue(new DateTimeImmutable($placedAt), new DateTimeImmutable('2026-09-04 12:00:00')))
        ->toBe($overdue);
})->with([
    'placed this morning' => ['2026-09-04 08:00:00', false],
    'placed two days ago to the hour' => ['2026-09-02 12:00:00', false],
    'placed two days and an hour ago' => ['2026-09-02 11:00:00', true],
    'placed a week ago' => ['2026-08-28 12:00:00', true],
]);

it('counts the whole queue in the heading while the panel shows the head of it', function (): void {
    $groups = attentionGroups(toShip: new AttentionRows([attentionRow(), attentionRow()], 9));

    expect($groups[0]->title)->toBe('9 orders to ship')
        ->and($groups[0]->rows)->toHaveCount(2)
        ->and($groups[0]->hidden())->toBe(7);
});

it('hides nothing when the panel shows the whole queue', function (): void {
    $groups = attentionGroups(toShip: AttentionRows::of([attentionRow()]));

    expect($groups[0]->hidden())->toBe(0);
});
