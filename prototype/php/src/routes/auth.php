<?php

use App\Http\Controllers\Auth\CustomerLoginController;
use App\Http\Controllers\Auth\MagicLinkVerificationController;
use App\Http\Controllers\Auth\SellerLoginController;
use App\Http\Controllers\Auth\SignOutController;
use Illuminate\Support\Facades\Route;

// Magic-link routes land here; both sites share them, so they carry no prefix.
Route::name('auth.')->group(function (): void {
    Route::get('/seller/login', [SellerLoginController::class, 'show'])->name('seller.login');
    Route::post('/seller/login', [SellerLoginController::class, 'send'])->name('seller.send');
    Route::post('/seller/logout', [SignOutController::class, 'seller'])->name('seller.logout');

    Route::get('/login', [CustomerLoginController::class, 'show'])->name('customer.login');
    Route::post('/login', [CustomerLoginController::class, 'send'])->name('customer.send');
    Route::post('/logout', [SignOutController::class, 'customer'])->name('customer.logout');

    // One endpoint for both sites: the link row names the actor it signs in.
    Route::get('/auth/magic/{token}', MagicLinkVerificationController::class)->name('magic.verify');
});
