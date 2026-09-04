<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Analytics\ChangeDirection;

it('reads a count against the range before it', function (int $count, int $previous, string $text, ChangeDirection $direction): void {
    $total = ActivityTotal::between('Views', $count, $previous);

    expect($total->label)->toBe('Views')
        ->and($total->count)->toBe($count)
        ->and($total->change->text)->toBe($text)
        ->and($total->change->direction)->toBe($direction);
})->with([
    'up' => [200, 100, '+100.0%', ChangeDirection::Up],
    'down' => [50, 100, '−50.0%', ChangeDirection::Down],
    'level' => [100, 100, '0.0%', ChangeDirection::Flat],
    'first range with any of them' => [7, 0, 'new', ChangeDirection::Flat],
]);

it('separates the thousands in the figure it renders', function (): void {
    expect(ActivityTotal::between('Views', 12345, 0)->figure())->toBe('12,345');
});
