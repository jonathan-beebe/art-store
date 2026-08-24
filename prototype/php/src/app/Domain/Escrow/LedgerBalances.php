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
}
