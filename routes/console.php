<?php

use App\Jobs\SendMarketplacePush;
use App\Models\MarketplaceNotification;
use App\Models\RentalReservation;
use App\Models\User;
use App\Services\PartOrderService;
use App\Services\SavedSearchMatcher;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('marketplace:admin {email}', function () {
    $user = User::where('email', mb_strtolower($this->argument('email')))->first();
    if (! $user) {
        $this->error('Foydalanuvchi topilmadi. Avval ro‘yxatdan o‘ting.');

        return 1;
    }
    $user->forceFill(['is_admin' => true])->save();
    $this->info('Administrator huquqi berildi.');

    return 0;
})->purpose('Promote an existing marketplace user to administrator');

Schedule::command('sanctum:prune-expired --hours=24')->daily();

Artisan::command('marketplace:maintenance', function () {
    $matches = app(SavedSearchMatcher::class)->run();
    $expired = app(PartOrderService::class)->expire();
    RentalReservation::where('status', 'pending')->where('expires_at', '<=', now())->update(['status' => 'cancelled', 'expires_at' => null]);
    if (config('firebase.enabled')) {
        MarketplaceNotification::where('push_status', 'pending')->where('created_at', '<', now()->subDay())->update(['push_status' => 'expired']);
        foreach (MarketplaceNotification::where('push_status', 'pending')->oldest()->limit(100)->get(['id']) as $notification) {
            SendMarketplacePush::dispatch($notification->id);
        }
    }
    $this->info('Search matches: '.$matches.'; expired orders: '.$expired);
})->purpose('Match saved searches, expire stock reservations and queue pending push notifications');

Schedule::command('marketplace:maintenance')->everyMinute()->withoutOverlapping(5);
