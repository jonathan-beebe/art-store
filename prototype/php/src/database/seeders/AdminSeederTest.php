<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;

it('seeds the two platform admins, verified, in order', function (): void {
    $this->seed(AdminSeeder::class);

    $admins = Admin::orderBy('id')->get();

    expect($admins->pluck('email')->all())->toBe(['jonathan-beebe@outlook.com', 'annaschmunk@pm.me']);

    foreach ($admins as $admin) {
        expect($admin->email_verified_at)->not->toBeNull();
    }
});

it('changes nothing on a second run', function (): void {
    $this->seed(AdminSeeder::class);
    $this->seed(AdminSeeder::class);

    expect(Admin::count())->toBe(2);
});
