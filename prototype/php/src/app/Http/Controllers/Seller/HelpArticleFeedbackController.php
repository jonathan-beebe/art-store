<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Seller\HelpArticleFeedbackCollapse;
use App\Seller\HelpArticles;
use Illuminate\Http\RedirectResponse;

/**
 * The help article page's "Did this answer it?" buttons — one controller
 * for both, the route's own `outcome` default naming which
 * {@see AnalyticsEventName} case a submission records. Not an Eloquent
 * resource — an unrecognised slug 404s the same way
 * {@see HelpArticleController} does.
 */
final class HelpArticleFeedbackController extends SellerController
{
    public function __invoke(string $article, string $outcome, HelpArticles $helpArticles, Analytics $analytics): RedirectResponse
    {
        $found = $helpArticles->find($article);

        abort_if($found === null, 404);

        $name = AnalyticsEventName::from($outcome);
        $seller = $this->seller();
        $now = $this->now();

        $analytics->recordEvent(AnalyticsEvent::forHelpArticle(
            $name,
            $found->slug,
            $seller->id,
            $now,
            HelpArticleFeedbackCollapse::dedupeKey($name, $found->slug, $seller->id, $now),
        ));

        return $name === AnalyticsEventName::HelpAnswered
            ? redirect()->route('seller.support.articles.show', $found->slug)->with('status', 'Thanks — glad it helped.')
            : redirect()->route('seller.support.create');
    }
}
