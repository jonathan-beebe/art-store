<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use RuntimeException;

/**
 * Shared ground for the admin pages that read the admin behind the request:
 * the ones that scope what they show or send by who is signed in.
 */
abstract class AdminController extends Controller
{
    protected function admin(): Admin
    {
        return auth('admin')->user() ?? throw new RuntimeException('The admin site runs behind the auth.admin middleware.');
    }
}
