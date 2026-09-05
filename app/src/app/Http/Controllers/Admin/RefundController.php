<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Fulfillment\RefundFulfillment;
use App\Http\Requests\Admin\RefundFulfillmentRequest;
use App\Models\Fulfillment;
use Illuminate\Http\RedirectResponse;

final class RefundController extends AdminController
{
    public function __invoke(
        RefundFulfillmentRequest $request,
        Fulfillment $fulfillment,
        RefundFulfillment $refundFulfillment,
    ): RedirectResponse {
        $refundFulfillment($fulfillment, $this->admin(), $request->reason(), $this->now());

        return redirect()
            ->route('admin.fulfillments.show', $fulfillment)
            ->with('status', 'Refund issued.');
    }
}
