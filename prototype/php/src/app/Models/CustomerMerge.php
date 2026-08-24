<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\CustomerMergeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['anonymous_customer_id', 'customer_id'])]
class CustomerMerge extends Model
{
    /** @use HasFactory<CustomerMergeFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'cmg';
    }

    /** @return BelongsTo<Customer, $this> */
    public function anonymousCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'anonymous_customer_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
