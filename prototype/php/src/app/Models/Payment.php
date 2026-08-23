<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\Money;
use App\Domain\Payments\DeclineReason;
use App\Domain\Payments\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

#[Fillable(['order_id', 'status', 'amount_cents', 'card_last_four', 'decline_reason', 'processed_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'decline_reason' => DeclineReason::class,
            'amount_cents' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function amount(): Money
    {
        return Money::fromCents($this->amount_cents);
    }
}
