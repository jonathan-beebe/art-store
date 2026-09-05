<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Models\FulfillmentFlow;
use Illuminate\Http\RedirectResponse;

/**
 * The flow editor's old address. A seller who bookmarked or linked it lands
 * on their default workflow's edit page, or the workflows list when they
 * have not made one yet.
 */
final class LegacyFlowRedirectController extends SellerController
{
    private const int PERMANENT = 301;

    public function __invoke(): RedirectResponse
    {
        $flow = $this->seller()->defaultFulfillmentFlow;

        return $flow instanceof FulfillmentFlow
            ? redirect()->route('seller.workflows.edit', $flow, self::PERMANENT)
            : redirect()->route('seller.workflows.index', [], self::PERMANENT);
    }
}
