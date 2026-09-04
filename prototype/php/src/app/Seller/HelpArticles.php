<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\HelpArticle;
use Illuminate\Support\Facades\File;
use SplFileInfo;

/**
 * The help articles under `resources/help/seller/*.md`, parsed once per
 * request and cached — the same handful of files back both the support
 * hub's grouped list and every article page.
 */
final class HelpArticles
{
    /**
     * The order groups list in, whatever order their files happen to sort
     * in. A group the taxonomy has not named yet sorts after every named
     * one rather than failing.
     */
    private const array GROUP_ORDER = ['Getting paid', 'Shipping', 'Listings', 'Messages'];

    /** @var list<HelpArticle>|null */
    private static ?array $cache = null;

    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return list<HelpArticle> every article, grouped and then ordered by position within its group
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $articles = collect(File::files(resource_path('help/seller')))
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'md')
            ->map(fn (SplFileInfo $file): HelpArticle => HelpArticle::fromMarkdown(File::get($file->getPathname())))
            ->all();

        usort(
            $articles,
            fn (HelpArticle $a, HelpArticle $b): int => [self::groupRank($a->group), $a->position]
                <=> [self::groupRank($b->group), $b->position],
        );

        return self::$cache = $articles;
    }

    public static function find(string $slug): ?HelpArticle
    {
        foreach (self::all() as $article) {
            if ($article->slug === $slug) {
                return $article;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<HelpArticle>> group title => its articles, in GROUP_ORDER
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::all() as $article) {
            $grouped[$article->group][] = $article;
        }

        return $grouped;
    }

    private static function groupRank(string $group): int
    {
        $rank = array_search($group, self::GROUP_ORDER, true);

        return $rank === false ? count(self::GROUP_ORDER) : $rank;
    }
}
