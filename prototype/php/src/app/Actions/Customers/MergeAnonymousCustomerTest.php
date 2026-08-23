<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\CustomerMerge;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('records the merge so a stale cookie still resolves', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    $this->assertDatabaseHas('customer_merges', [
        'anonymous_customer_id' => $anonymous->id,
        'customer_id' => $verified->id,
    ]);
});

it('returns the customer the history moved to', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();

    $merged = app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect($verified->is($merged))->toBeTrue();
});

it('leaves the anonymous row in place for the merge trail', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    $this->assertDatabaseHas('customers', ['id' => $anonymous->id]);
});

it('re-points rows in a customer-owned table', function (): void {
    // The commerce tables carry columns this test knows nothing about, so the
    // table-driven re-pointing is proven against a row this test can write on its own.
    Schema::dropIfExists('favorites');
    Schema::create('favorites', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('customer_id');
    });
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $bystander = Customer::factory()->create();
    DB::table('favorites')->insert([
        ['customer_id' => $anonymous->id],
        ['customer_id' => $bystander->id],
    ]);

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect(DB::table('favorites')->where('customer_id', $verified->id)->count())->toBe(1)
        ->and(DB::table('favorites')->where('customer_id', $anonymous->id)->count())->toBe(0)
        ->and(DB::table('favorites')->where('customer_id', $bystander->id)->count())->toBe(1);
});

it('skips a customer-owned table that does not exist', function (): void {
    Schema::dropIfExists('favorites');
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();

    app(MergeAnonymousCustomer::class)($anonymous, $verified);

    expect(CustomerMerge::count())->toBe(1);
});
