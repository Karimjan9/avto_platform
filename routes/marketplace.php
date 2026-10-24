<?php

use App\Http\Controllers\Api\V1\BusinessCabinetController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PartOrderController;
use App\Http\Controllers\Api\V1\PartProductController;
use App\Http\Controllers\Api\V1\PartRequestController;
use App\Http\Controllers\Api\V1\RentalController;
use App\Http\Controllers\Api\V1\SavedSearchController;
use App\Http\Controllers\Api\V1\TrustController;
use App\Http\Controllers\Api\V1\VehicleCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('catalog/vehicles/{kind}', [VehicleCatalogController::class, 'index'])->whereIn('kind', ['makes', 'models', 'generations', 'variants']);
Route::get('parts', [PartProductController::class, 'index']);
Route::get('parts/{product}', [PartProductController::class, 'show'])->whereNumber('product');
Route::get('parts/{product}/offers', [PartProductController::class, 'offers'])->whereNumber('product');
Route::get('businesses/{business}/listings', [BusinessCabinetController::class, 'listings'])->whereNumber('business');
Route::get('listings/{listing}/availability', [RentalController::class, 'availability'])->whereNumber('listing');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('part-products', [PartProductController::class, 'store']);
    Route::get('me/part-products', [PartProductController::class, 'mine']);
    Route::get('me/saved-searches', [SavedSearchController::class, 'index']);
    Route::post('me/saved-searches', [SavedSearchController::class, 'store']);
    Route::patch('me/saved-searches/{search}', [SavedSearchController::class, 'update']);
    Route::delete('me/saved-searches/{search}', [SavedSearchController::class, 'destroy']);
    Route::get('me/notifications', [NotificationController::class, 'index']);
    Route::patch('me/notifications/{notification}/read', [NotificationController::class, 'read']);
    Route::post('me/push-devices', [NotificationController::class, 'registerDevice']);
    Route::delete('me/push-devices/{device}', [NotificationController::class, 'removeDevice']);
    Route::get('me/part-requests', [PartRequestController::class, 'index']);
    Route::post('me/part-requests', [PartRequestController::class, 'store']);
    Route::get('me/part-requests/{partRequest}', [PartRequestController::class, 'show']);
    Route::patch('me/part-requests/{partRequest}/close', [PartRequestController::class, 'close']);
    Route::post('me/part-requests/{partRequest}/quotes/{quote}/accept', [PartRequestController::class, 'accept']);
    Route::post('businesses/{business}/inquiries', [BusinessCabinetController::class, 'inquire'])->whereNumber('business');
    Route::get('me/inquiries', [BusinessCabinetController::class, 'myInquiries']);
    Route::get('me/orders', [PartOrderController::class, 'index']);
    Route::post('me/orders', [PartOrderController::class, 'store']);
    Route::get('me/orders/{order}', [PartOrderController::class, 'show']);
    Route::patch('me/orders/{order}/cancel', [PartOrderController::class, 'cancel']);
    Route::get('me/rentals', [RentalController::class, 'index']);
    Route::post('listings/{listing}/reservations', [RentalController::class, 'store'])->whereNumber('listing');
    Route::patch('me/rentals/{reservation}/cancel', [RentalController::class, 'cancel']);
    Route::get('me/listings/{listing}/calendar', [RentalController::class, 'calendar']);
    Route::post('me/listings/{listing}/calendar-blocks', [RentalController::class, 'block']);
    Route::patch('me/listings/{listing}/calendar/{reservation}', [RentalController::class, 'status']);

    Route::prefix('me/businesses/{business}')->group(function () {
        Route::get('inventory', [BusinessCabinetController::class, 'inventory']);
        Route::post('branches', [BusinessCabinetController::class, 'saveBranch']);
        Route::put('branches/{branch}', [BusinessCabinetController::class, 'saveBranch']);
        Route::get('members', [BusinessCabinetController::class, 'members']);
        Route::put('members', [BusinessCabinetController::class, 'saveMember']);
        Route::delete('members/{member}', [BusinessCabinetController::class, 'removeMember']);
        Route::patch('inventory/{listing}/stock', [BusinessCabinetController::class, 'stock']);
        Route::post('import', [BusinessCabinetController::class, 'import'])->middleware(['phone.verified', 'seller.ready']);
        Route::get('inquiries', [BusinessCabinetController::class, 'inquiries']);
        Route::patch('inquiries/{inquiry}', [BusinessCabinetController::class, 'reply']);
        Route::get('part-requests', [PartRequestController::class, 'businessRequests']);
        Route::post('part-requests/{partRequest}/quotes', [PartRequestController::class, 'quote']);
        Route::get('orders', [PartOrderController::class, 'businessOrders']);
        Route::patch('orders/{order}', [PartOrderController::class, 'status']);
    });
    Route::prefix('admin')->middleware('can:marketplace-admin')->group(function () {
        Route::post('catalog/vehicles/{kind}', [VehicleCatalogController::class, 'store'])->whereIn('kind', ['makes', 'models', 'generations', 'variants']);
        Route::get('part-products', [PartProductController::class, 'pending']);
        Route::patch('part-products/{product}/moderation', [PartProductController::class, 'moderate']);
        Route::post('verifications', [TrustController::class, 'verify']);
        Route::put('listings/{listing}/promotion', [TrustController::class, 'promote']);
    });
});
