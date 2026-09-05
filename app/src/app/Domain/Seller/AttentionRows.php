<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * What one focus group found: the rows it shows, and how many the queue
 * behind them holds. A group's heading counts the whole queue while the
 * panel lists the head of it, so a seller reads the true size of the pile
 * and still gets a panel they can scan.
 */
final readonly class AttentionRows
{
    /**
     * @param  list<AttentionRow>  $shown
     */
    public function __construct(
        public array $shown,
        public int $total,
    ) {}

    /**
     * A queue short enough to show whole.
     *
     * @param  list<AttentionRow>  $rows
     */
    public static function of(array $rows): self
    {
        return new self($rows, count($rows));
    }
}
