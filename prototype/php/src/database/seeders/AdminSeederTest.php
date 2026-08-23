<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;

it('seeds one verified admin', function (): void {
    $this->seed(AdminSeeder::class);

    $admin = Admin::where('email', AdminSeeder::EMAIL)->sole();

    expect($admin->email_verified_at)->not->toBeNull();
});

it('changes nothing on a second run', function (): void {
    $this->seed(AdminSeeder::class);
    $this->seed(AdminSeeder::class);

    expect(Admin::count())->toBe(1);
});
