<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use DateTimeImmutable;
use Illuminate\Database\Seeder;

/**
 * The admins `/admin/login` admits. `ADMINS[0]` is seeded first, making it
 * `Admin::platformAdmin()` — the one every support thread opens against.
 */
class AdminSeeder extends Seeder
{
    /**
     * @var list<array{email: string, name: string}>
     */
    public const ADMINS = [
        ['email' => 'jonathan-beebe@outlook.com', 'name' => 'Jonathan Beebe'],
        ['email' => 'annaschmunk@pm.me', 'name' => 'Anna Schmunk'],
    ];

    public function run(): void
    {
        foreach (self::ADMINS as $admin) {
            Admin::firstOrCreate(
                ['email' => $admin['email']],
                [
                    'name' => $admin['name'],
                    'email_verified_at' => new DateTimeImmutable('2026-06-01 00:00:00'),
                ],
            );
        }
    }
}
