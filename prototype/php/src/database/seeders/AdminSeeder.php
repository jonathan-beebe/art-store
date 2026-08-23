<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use DateTimeImmutable;
use Illuminate\Database\Seeder;

/**
 * The one admin `/admin/login` admits and the one every support thread opens
 * against (`Admin::platformAdmin()`).
 */
class AdminSeeder extends Seeder
{
    public const EMAIL = 'admin@example.com';

    public function run(): void
    {
        Admin::firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Reese Calloway',
                'email_verified_at' => new DateTimeImmutable('2026-06-01 00:00:00'),
            ],
        );
    }
}
