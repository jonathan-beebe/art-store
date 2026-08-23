<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\View\View;

final class DashboardController extends AdminController
{
    public function __invoke(): View
    {
        return view('admin.dashboard');
    }
}
