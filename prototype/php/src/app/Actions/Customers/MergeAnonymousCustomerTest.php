<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\CustomerMerge;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class MergeAnonymousCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_the_merge_so_a_stale_cookie_still_resolves(): void
    {
        $anonymous = Customer::factory()->anonymous()->create();
        $verified = Customer::factory()->create();

        $this->merge($anonymous, $verified);

        $this->assertDatabaseHas('customer_merges', [
            'anonymous_customer_id' => $anonymous->id,
            'customer_id' => $verified->id,
        ]);
    }

    public function test_it_returns_the_customer_the_history_moved_to(): void
    {
        $anonymous = Customer::factory()->anonymous()->create();
        $verified = Customer::factory()->create();

        $this->assertTrue($verified->is($this->merge($anonymous, $verified)));
    }

    public function test_it_leaves_the_anonymous_row_in_place_for_the_merge_trail(): void
    {
        $anonymous = Customer::factory()->anonymous()->create();
        $verified = Customer::factory()->create();

        $this->merge($anonymous, $verified);

        $this->assertDatabaseHas('customers', ['id' => $anonymous->id]);
    }

    public function test_it_re_points_rows_in_a_customer_owned_table(): void
    {
        $this->replaceFavoritesWithAStandIn();
        $anonymous = Customer::factory()->anonymous()->create();
        $verified = Customer::factory()->create();
        $bystander = Customer::factory()->create();
        DB::table('favorites')->insert([
            ['customer_id' => $anonymous->id],
            ['customer_id' => $bystander->id],
        ]);

        $this->merge($anonymous, $verified);

        $this->assertSame(1, DB::table('favorites')->where('customer_id', $verified->id)->count());
        $this->assertSame(0, DB::table('favorites')->where('customer_id', $anonymous->id)->count());
        $this->assertSame(1, DB::table('favorites')->where('customer_id', $bystander->id)->count());
    }

    public function test_it_skips_a_customer_owned_table_that_does_not_exist(): void
    {
        Schema::dropIfExists('favorites');
        $anonymous = Customer::factory()->anonymous()->create();
        $verified = Customer::factory()->create();

        $this->merge($anonymous, $verified);

        $this->assertSame(1, CustomerMerge::count());
    }

    private function merge(Customer $anonymous, Customer $verified): Customer
    {
        return $this->app->call(fn (MergeAnonymousCustomer $merge) => $merge($anonymous, $verified));
    }

    /**
     * The commerce tables carry columns this ticket knows nothing about, so
     * the table-driven re-pointing is proven against a row this test can write
     * on its own.
     */
    private function replaceFavoritesWithAStandIn(): void
    {
        Schema::dropIfExists('favorites');

        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id');
        });
    }
}
