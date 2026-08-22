<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['anonymous_customer_id', 'customer_id'])]
class CustomerMerge extends Model
{
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
