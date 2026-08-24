<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

/**
 * Every seller's balance at once, folded from movements the caller read in
 * one go. A page that lists sellers reads the ledger once and asks this for
 * each row it renders, rather than asking each seller for a balance of its
 * own; a seller with no entries answers zero without a read at all.
 */
final readonly class LedgerBalances
{
    /**
     * @param  array<string, LedgerBalance>  $bySeller
     */
    private function __construct(private array $bySeller) {}

    /**
     * @param  array<string, list<LedgerMovement>>  $movementsBySeller  seller id => that seller's movements
     */
    public static function from(array $movementsBySeller): self
    {
        return new self(array_map(
            fn (array $movements): LedgerBalance => LedgerBalance::from($movements),
            $movementsBySeller,
        ));
    }

    public function of(string $sellerId): LedgerBalance
    {
        return $this->bySeller[$sellerId] ?? LedgerBalance::from([]);
    }

    /**
     * Every seller's balance added together — the platform's own, for
     * `/admin` and `/admin/accounting`. Free of a second ledger read:
     * {@see LedgerBalance::combine()} is why summing balances already folded
     * per seller agrees with folding the whole ledger at once.
     */
    public function total(): LedgerBalance
    {
        return LedgerBalance::combine(array_values($this->bySeller));
    }
}
