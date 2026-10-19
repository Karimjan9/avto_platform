<?php

namespace App\Services;

use App\Models\MarketplaceNotification;
use App\Models\PushDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FcmClient
{
    public function send(PushDevice $device, MarketplaceNotification $notification): bool
    {
        $project = config('firebase.project_id');
        if (! is_string($project) || ! preg_match('/^[a-z][a-z0-9-]{4,62}$/', $project)) {
            throw new \RuntimeException('Firebase Project ID is not configured.');
        }
        $response = Http::withToken($this->accessToken())->timeout(8)->connectTimeout(3)
            ->post('https://fcm.googleapis.com/v1/projects/'.$project.'/messages:send', ['message' => [
                'token' => $device->token,
                'notification' => ['title' => $notification->title, 'body' => 'Tafsilotlarni ilovada ko‘ring.'],
                'data' => ['notification_id' => $notification->id, 'type' => $notification->type],
                'android' => ['notification' => ['tag' => $notification->id]],
                'apns' => ['headers' => ['apns-collapse-id' => $notification->id]],
            ]]);
        $codes = collect($response->json('error.details', []))->pluck('errorCode');
        if ($codes->contains('UNREGISTERED')) {
            $device->update(['is_active' => false]);

            return false;
        }
        if ($response->status() === 401) {
            Cache::forget($this->cacheKey());
        }
        if (! $response->successful()) {
            throw new \RuntimeException('FCM returned HTTP '.$response->status());
        }

        return true;
    }

    private function cacheKey(): string
    {
        return 'firebase-oauth:'.hash('sha256', (string) config('firebase.credentials'));
    }

    private function accessToken(): string
    {
        return Cache::remember($this->cacheKey(), 3000, function () {
            $path = config('firebase.credentials');
            if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
                throw new \RuntimeException('Firebase credentials file is not configured.');
            }
            $credentials = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (! isset($credentials['client_email'], $credentials['private_key'])) {
                throw new \RuntimeException('Invalid Firebase credentials file.');
            }
            $encode = fn ($value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
            $header = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $encode(json_encode(['iss' => $credentials['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => time(), 'exp' => time() + 3600]));
            if (! openssl_sign($header.'.'.$claims, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Firebase signing failed.');
            }
            $assertion = $header.'.'.$claims.'.'.$encode($signature);
            $response = Http::asForm()->timeout(8)->connectTimeout(3)->post('https://oauth2.googleapis.com/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion]);
            $token = $response->json('access_token');
            if (! $response->successful() || ! is_string($token) || $token === '') {
                throw new \RuntimeException('Firebase authorization failed.');
            }

            return $token;
        });
    }
}
