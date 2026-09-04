<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Seller\HelpArticles;
use Illuminate\View\View;

/**
 * One help article, read by its slug. Not an Eloquent resource — an
 * unrecognised slug 404s the same way a missing model would, without a
 * table behind it.
 */
final class HelpArticleController extends SellerController
{
    public function show(string $article): View
    {
        $found = HelpArticles::find($article);

        if ($found === null) {
            abort(404);
        }

        return view('seller.support.article', ['article' => $found]);
    }
}
