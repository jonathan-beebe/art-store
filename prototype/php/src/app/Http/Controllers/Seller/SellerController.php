<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use DateTimeImmutable;
use RuntimeException;

/**
 * Shared ground for the seller portal pages: the seller behind the request,
 * and the clock the actions they run are given.
 */
abstract class SellerController extends Controller
{
    protected function seller(): Seller
    {
        return auth('seller')->user() ?? throw new RuntimeException('The seller portal runs behind the auth.seller middleware.');
    }

    protected function now(): DateTimeImmutable
    {
        return now()->toDateTimeImmutable();
    }
}
