<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Funnel;

it('renders a confirmation page naming the funnel before it is deleted', function (): void {
    $funnel = Funnel::factory()->create(['name' => 'Gift Shopping']);

    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.funnels.delete', $funnel));

    $response->assertOk();
    $response->assertSee('Delete Gift Shopping?');
    expect(Funnel::query()->find($funnel->id))->not->toBeNull();
});
