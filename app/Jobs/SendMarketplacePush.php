<?php

namespace App\Jobs;

use App\Models\MarketplaceNotification;
use App\Models\PushDelivery;
use App\Models\PushDevice;
use App\Services\FcmClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMarketplacePush implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 55;

    public int $uniqueFor = 600;

    public function __construct(public string $notificationId) {}

    public function uniqueId(): string
    {
        return $this->notificationId;
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(FcmClient $client): void
    {
        if (! config('firebase.enabled')) {
            return;
        }
        $notification = MarketplaceNotification::find($this->notificationId);
        if (! $notification || $notification->push_status === 'sent') {
            return;
        }
        $devices = PushDevice::where('user_id', $notification->user_id)->where('is_active', true)->get();
        foreach ($devices as $device) {
            $delivery = PushDelivery::firstOrCreate(['marketplace_notification_id' => $notification->id, 'push_device_id' => $device->id]);
            if ($delivery->sent_at !== null) {
                continue;
            }
            if ($client->send($device, $notification)) {
                $delivery->update(['sent_at' => now()]);
            }
        }
        $notification->update(['push_status' => $devices->isEmpty() ? 'no_devices' : 'sent']);
    }

    public function failed(?\Throwable $exception): void
    {
        MarketplaceNotification::whereKey($this->notificationId)->update(['push_status' => 'failed']);
    }
}
