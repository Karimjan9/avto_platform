<?php

namespace App\Services;

use App\Jobs\SendMarketplacePush;
use App\Models\Business;
use App\Models\MarketplaceNotification;
use Illuminate\Support\Str;

class MarketplaceNotifier
{
    public function user(int $userId, string $type, string $title, array $data, ?string $key = null): MarketplaceNotification
    {
        $values = ['id' => (string) Str::uuid(), 'user_id' => $userId, 'type' => $type, 'title' => $title, 'data' => $data, 'push_status' => 'pending'];
        $notification = $key ? MarketplaceNotification::firstOrCreate(['deduplication_key' => $key], $values) : MarketplaceNotification::create($values);
        if ($notification->wasRecentlyCreated && config('firebase.enabled')) {
            SendMarketplacePush::dispatch($notification->id)->afterCommit();
        }

        return $notification;
    }

    public function business(Business $business, string $permission, string $type, string $title, array $data, string $key): void
    {
        $users = $business->members()->whereJsonContains('permissions', $permission)->pluck('user_id')->push($business->user_id)->unique();
        foreach ($users as $userId) {
            $this->user($userId, $type, $title, $data, $key.':'.$userId);
        }
    }
}
