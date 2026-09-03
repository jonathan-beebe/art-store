<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use Illuminate\View\View;

/**
 * `/admin/funnels/{funnel}/delete`: the page a "Delete" link visits before
 * `FunnelController::destroy()` runs — a form post is the only thing on it,
 * so removing a funnel always takes two steps, never one click.
 */
final class FunnelDeleteController extends Controller
{
    public function __invoke(Funnel $funnel): View
    {
        return view('admin.funnels.delete', ['funnel' => $funnel]);
    }
}
