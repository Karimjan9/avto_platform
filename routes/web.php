<?php

use App\Http\Controllers\Web\CabinetController;
use App\Http\Controllers\Web\MarketplaceController;
use App\Http\Controllers\Web\SessionController;
use App\Http\Controllers\Web\PhoneAuthController;
use App\Http\Controllers\Web\SellerOnboardingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketplaceController::class, 'index'])->middleware('throttle:marketplace-web')->name('home');
Route::get('/elonlar', [MarketplaceController::class, 'index'])->middleware('throttle:marketplace-web')->name('listings');
Route::get('/elonlar/{listing}', [MarketplaceController::class, 'show'])->middleware('throttle:marketplace-web')->whereNumber('listing')->name('listing');
Route::middleware('guest')->group(function () {
    Route::get('/kirish', [SessionController::class, 'create'])->name('login');
    Route::get('/kirish/email', [SessionController::class, 'email'])->name('login.email');
    Route::post('/kirish', [SessionController::class, 'store'])->middleware('throttle:marketplace-auth');
    Route::get('/royxatdan-otish', [SessionController::class, 'register'])->name('register');
    Route::post('/royxatdan-otish', [SessionController::class, 'saveRegistration'])->middleware('throttle:marketplace-auth');
});
Route::middleware('cache.headers:private;no_store;max_age=0')->group(function () {
    Route::get('/telefonni-tasdiqlash', [PhoneAuthController::class, 'create'])->name('phone.start');
    Route::post('/kirish/telefon', [PhoneAuthController::class, 'send'])->middleware('throttle:marketplace-auth')->name('phone.send');
    Route::get('/telefon/kod', [PhoneAuthController::class, 'show'])->name('phone.verify');
    Route::post('/telefon/kod', [PhoneAuthController::class, 'verify'])->middleware('throttle:marketplace-auth')->name('phone.verify.store');
    Route::post('/telefon/kod/qayta', [PhoneAuthController::class, 'resend'])->middleware('throttle:marketplace-auth')->name('phone.resend');
});
Route::middleware(['auth', 'cache.headers:private;no_store;max_age=0'])->group(function () {
    Route::post('/chiqish', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/kabinet', [CabinetController::class, 'index'])->name('cabinet');
    Route::middleware('phone.verified')->group(function () {
        Route::get('/sotuvchi/sozlash', [SellerOnboardingController::class, 'show'])->name('seller.onboarding');
        Route::post('/sotuvchi/sozlash', [SellerOnboardingController::class, 'store'])->name('seller.onboarding.store');
        Route::get('/kabinet/profil', [SellerOnboardingController::class, 'edit'])->name('seller.profile');
        Route::patch('/kabinet/profil', [SellerOnboardingController::class, 'update'])->name('seller.profile.update');
        Route::middleware('seller.ready')->group(function () {
            Route::get('/kabinet/elonlar/yangi', [CabinetController::class, 'listing'])->name('cabinet.listing.create');
            Route::get('/kabinet/elonlar/{listing}/tahrirlash', [CabinetController::class, 'listing'])->whereNumber('listing')->name('cabinet.listing.edit');
            Route::patch('/kabinet/elonlar/{listing}/holat', [CabinetController::class, 'status'])->whereNumber('listing')->name('cabinet.listing.status');
        });
    });
    Route::get('/kabinet/biznes/{business}/ombor', [CabinetController::class, 'inventory'])->whereNumber('business')->name('cabinet.inventory');
    Route::get('/kabinet/arenda/{listing}', [CabinetController::class, 'calendar'])->whereNumber('listing')->name('cabinet.calendar');
    Route::get('/admin', [CabinetController::class, 'admin'])->middleware('can:marketplace-admin')->name('admin');
    Route::get('/admin/elonlar/{listing}', [CabinetController::class, 'review'])->whereNumber('listing')->middleware('can:marketplace-admin')->name('admin.review');
});

Route::view('/1', 'design-preview', [
    'previewImage' => 'design-previews/red.png',
    'previewTitle' => 'BuxoroAvto — qizil dizayn',
])->name('design-preview.red');

Route::view('/2', 'design-preview', [
    'previewImage' => 'design-previews/yellow.png',
    'previewTitle' => 'BuxoroAvto — sariq dizayn',
])->name('design-preview.yellow');

Route::get('/mos-detallar', \App\Http\Controllers\Web\PartFinderController::class)->middleware('throttle:marketplace-web')->name('parts.find');
