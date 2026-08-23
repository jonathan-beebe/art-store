<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Database\Seeder;

/**
 * Four verified sellers a reviewer can sign in as through the debug magic
 * link on first load.
 */
class SellerSeeder extends Seeder
{
    public const MAYA_EMAIL = 'maya@example.com';

    public const NOAH_EMAIL = 'noah@example.com';

    public const PRIYA_EMAIL = 'priya@example.com';

    public const LEO_EMAIL = 'leo@example.com';

    public function run(): void
    {
        $verifiedAt = new DateTimeImmutable('2026-06-01 00:00:00');

        foreach ($this->sellers($verifiedAt) as $seller) {
            Seller::create($seller);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sellers(DateTimeImmutable $verifiedAt): array
    {
        return [
            [
                'email' => self::MAYA_EMAIL,
                'name' => 'Maya Reyes',
                'shop_name' => 'Terra & Glaze Ceramics',
                'email_verified_at' => $verifiedAt,
            ],
            [
                'email' => self::NOAH_EMAIL,
                'name' => 'Noah Chen',
                'shop_name' => 'North Light Editions',
                'email_verified_at' => $verifiedAt,
            ],
            [
                'email' => self::PRIYA_EMAIL,
                'name' => 'Priya Anand',
                'shop_name' => 'Priya Anand Textile Studio',
                'email_verified_at' => $verifiedAt,
            ],
            [
                'email' => self::LEO_EMAIL,
                'name' => 'Leo Martins',
                'shop_name' => 'Leo Martins Photography',
                'email_verified_at' => $verifiedAt,
            ],
        ];
    }
}
