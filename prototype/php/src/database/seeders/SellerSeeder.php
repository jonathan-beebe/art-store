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
    public const MOLLY_EMAIL = 'molly@example.com';

    public const DEAN_EMAIL = 'dean@example.com';

    public const SYBILL_EMAIL = 'sybill@example.com';

    public const COLIN_EMAIL = 'colin@example.com';

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
                'email' => self::MOLLY_EMAIL,
                'name' => 'Molly Weasley',
                'shop_name' => 'The Burrow Craftworks',
                'email_verified_at' => $verifiedAt,
            ],
            [
                'email' => self::DEAN_EMAIL,
                'name' => 'Dean Thomas',
                'shop_name' => 'Dean Thomas Studio',
                'email_verified_at' => $verifiedAt,
            ],
            [
                'email' => self::SYBILL_EMAIL,
                'name' => 'Sybill Trelawney',
                'shop_name' => "Trelawney's Tower Studio",
                'email_verified_at' => $verifiedAt,
            ],
            [
                'email' => self::COLIN_EMAIL,
                'name' => 'Colin Creevey',
                'shop_name' => 'Creevey Camera Works',
                'email_verified_at' => $verifiedAt,
            ],
        ];
    }
}
