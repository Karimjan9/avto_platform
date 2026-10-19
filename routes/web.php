<?php

use App\Http\Controllers\Web\CabinetController;
use App\Http\Controllers\Web\MarketplaceController;
use App\Http\Controllers\Web\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketplaceController::class, 'index'])->middleware('throttle:marketplace-web')->name('home');
Route::get('/elonlar', [MarketplaceController::class, 'index'])->middleware('throttle:marketplace-web')->name('listings');
Route::get('/elonlar/{listing}', [MarketplaceController::class, 'show'])->middleware('throttle:marketplace-web')->whereNumber('listing')->name('listing');
Route::middleware('guest')->group(function () {
    Route::get('/kirish', [SessionController::class, 'create'])->name('login');
    Route::post('/kirish', [SessionController::class, 'store'])->middleware('throttle:marketplace-auth');
    Route::get('/royxatdan-otish', [SessionController::class, 'register'])->name('register');
    Route::post('/royxatdan-otish', [SessionController::class, 'saveRegistration'])->middleware('throttle:marketplace-auth');
});
Route::middleware(['auth', 'cache.headers:private;no_store;max_age=0'])->group(function () {
    Route::post('/chiqish', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/kabinet', [CabinetController::class, 'index'])->name('cabinet');
    Route::get('/kabinet/elonlar/yangi', [CabinetController::class, 'listing'])->name('cabinet.listing.create');
    Route::get('/kabinet/elonlar/{listing}/tahrirlash', [CabinetController::class, 'listing'])->whereNumber('listing')->name('cabinet.listing.edit');
    Route::get('/kabinet/biznes/{business}/ombor', [CabinetController::class, 'inventory'])->whereNumber('business')->name('cabinet.inventory');
    Route::get('/kabinet/arenda/{listing}', [CabinetController::class, 'calendar'])->whereNumber('listing')->name('cabinet.calendar');
    Route::get('/admin', [CabinetController::class, 'admin'])->middleware('can:marketplace-admin')->name('admin');
    Route::get('/admin/elonlar/{listing}', [CabinetController::class, 'review'])->whereNumber('listing')->middleware('can:marketplace-admin')->name('admin.review');
});
