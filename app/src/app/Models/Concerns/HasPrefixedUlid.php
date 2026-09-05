<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Domain\Identifiers\PrefixedId;
use App\Identifiers\IdMint;
use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;

/**
 * A text primary key of the form `<prefix>_<ulid>`, minted when the row is
 * created. Building on the framework's own unique-string-id concern lets
 * `getKeyType()`, `getIncrementing()`, and route-model binding follow from
 * the key itself. No model declares them separately.
 *
 * The ULID is drawn from the application clock, so a seeder or a test that
 * freezes or travels time gets ids that sort in creation order the same way
 * ids drawn from the wall clock do. The random half stays random.
 *
 * Route-model binding refuses a value carrying another table's prefix the
 * same way it refuses one naming no row: the concern turns a foreign-prefixed
 * id into a `ModelNotFoundException`, which the site renders as its own 404.
 */
trait HasPrefixedUlid
{
    use HasUniqueStringIds;

    /**
     * The three letters every id of this table carries.
     */
    abstract public static function idPrefix(): string;

    public function newUniqueId(): string
    {
        return IdMint::of(static::idPrefix());
    }

    protected function isValidUniqueId(mixed $value): bool
    {
        return is_string($value) && PrefixedId::parse(static::idPrefix(), $value) !== null;
    }
}
