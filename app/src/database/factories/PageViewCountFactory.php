<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\PageViewSite;
use App\Models\PageViewCount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<PageViewCount>
 */
class PageViewCountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'site' => PageViewSite::Shop->value,
            'path_pattern' => '/art/{listing}',
            'day' => '2026-08-24',
            'count' => 1,
        ];
    }
}
