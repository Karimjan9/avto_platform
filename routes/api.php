<?php

use App\Http\Controllers\Api\V1\AdminBusinessController;
use App\Http\Controllers\Api\V1\AdminListingController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ListingController;
use App\Http\Controllers\Api\V1\ListingFormController;
use App\Http\Controllers\Api\V1\ListingImageController;
use App\Http\Controllers\Api\V1\MyBusinessController;
use App\Http\Controllers\Api\V1\MyListingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('throttle:marketplace-api')->group(function () {
    require __DIR__.'/marketplace.php';
    Route::get('catalog', CatalogController::class)->name('catalog');
    Route::get('catalog/form', ListingFormController::class)->name('catalog.form');
    Route::get('listings', [ListingController::class, 'index'])->name('listings.index');
    Route::get('listings/{listing}', [ListingController::class, 'show'])->whereNumber('listing')->name('listings.show');
    Route::get('businesses', [BusinessController::class, 'index'])->name('businesses.index');
    Route::get('businesses/{business}', [BusinessController::class, 'show'])->whereNumber('business')->name('businesses.show');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:marketplace-auth')->name('auth.register');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:marketplace-auth')->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::apiResource('me/listings', MyListingController::class)->except('update')->names('me.listings');
        Route::put('me/listings/{listing}', [MyListingController::class, 'update'])->name('me.listings.update');
        Route::patch('me/listings/{listing}/status', [MyListingController::class, 'status'])->name('me.listings.status');
        Route::post('me/listings/{listing}/images', [ListingImageController::class, 'store'])->name('me.listings.images.store');
        Route::delete('me/listings/{listing}/images/{image}', [ListingImageController::class, 'destroy'])->name('me.listings.images.destroy');
        Route::apiResource('me/businesses', MyBusinessController::class)->except('update')->names('me.businesses');
        Route::put('me/businesses/{business}', [MyBusinessController::class, 'update'])->name('me.businesses.update');

        Route::prefix('admin')->name('admin.')->middleware('can:marketplace-admin')->group(function () {
            Route::get('listings', [AdminListingController::class, 'index'])->name('listings.index');
            Route::patch('listings/{listing}/moderation', [AdminListingController::class, 'moderate'])->name('listings.moderate');
            Route::get('businesses', [AdminBusinessController::class, 'index'])->name('businesses.index');
            Route::patch('businesses/{business}/moderation', [AdminBusinessController::class, 'moderate'])->name('businesses.moderate');
        });
    });
});
