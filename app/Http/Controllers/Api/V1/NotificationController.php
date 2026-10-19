<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceNotification;
use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['unread' => ['sometimes', 'boolean'], 'per_page' => ['sometimes', 'integer', 'between:1,50']]);
        $query = MarketplaceNotification::where('user_id', $request->user()->id);
        if ($data['unread'] ?? false) {
            $query->whereNull('read_at');
        }

        return $query->latest('created_at')->orderByDesc('id')->paginate($data['per_page'] ?? 20);
    }

    public function read(Request $request, MarketplaceNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['data' => $notification]);
    }

    public function registerDevice(Request $request)
    {
        $data = $request->validate(['device_id' => ['required', 'string', 'max:100'], 'platform' => ['required', 'in:android,ios'], 'token' => ['required', 'string', 'min:20', 'max:4096']]);
        $device = DB::transaction(function () use ($request, $data) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', $data['token']);
            PushDevice::where('token_hash', $hash)->where(fn ($q) => $q->where('user_id', '!=', $user->id)->orWhere('device_id', '!=', $data['device_id']))->delete();
            $existing = PushDevice::where('user_id', $user->id)->where('device_id', $data['device_id'])->first();
            abort_if(! $existing && PushDevice::where('user_id', $user->id)->count() >= 5, 422, 'Qurilma limiti 5 ta.');

            return PushDevice::updateOrCreate(['user_id' => $user->id, 'device_id' => $data['device_id']], $data + ['token_hash' => $hash, 'last_seen_at' => now(), 'is_active' => true]);
        });

        return response()->json(['data' => $device], 201);
    }

    public function removeDevice(Request $request, PushDevice $device)
    {
        abort_unless($device->user_id === $request->user()->id, 403);
        $device->delete();

        return response()->noContent();
    }
}
