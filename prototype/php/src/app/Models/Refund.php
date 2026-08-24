<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;
use App\Domain\Money\Money;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Money sent back to a customer for one fulfillment, always the whole
 * subtotal. There is no gateway behind it: a refund row is the refund.
 *
 * @property-read Order $order
 * @property-read Fulfillment $fulfillment
 */
#[Fillable([
    'order_id', 'fulfillment_id', 'payment_id', 'amount_cents',
    'reason', 'issued_by_type', 'issued_by_id',
])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'rfd';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Fulfillment, $this> */
    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function amount(): Money
    {
        return Money::fromCents($this->amount_cents);
    }

    /**
     * Whether the seller declined the parcel or an admin settled a dispute.
     * The column is not cast, so the raw value stays readable next to the
     * `issued_by_id` it pairs with.
     */
    public function issuer(): ActorType
    {
        return ActorType::from($this->issued_by_type);
    }

    public function issuerLabel(): string
    {
        return ucfirst($this->issuer()->value);
    }
}
