<?php

namespace App\Services;

use App\Exceptions\SmsDeliveryException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EskizSmsGateway
{
    public function send(string $phone, string $message): void
    {
        if (! config('sms.enabled') || ! config('sms.eskiz.email') || ! config('sms.eskiz.password')) {
            throw new SmsDeliveryException;
        }

        try {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $response = Http::asMultipart()->acceptJson()->withToken($this->accessToken())
                    ->timeout(config('sms.eskiz.timeout'))->connectTimeout(config('sms.eskiz.connect_timeout'))
                    ->post(config('sms.eskiz.base_url').'/message/sms/send', [
                        'mobile_phone' => ltrim($phone, '+'),
                        'message' => $message,
                        'from' => config('sms.eskiz.sender'),
                    ]);
                if ($response->status() === 401 && $attempt === 0) {
                    $this->cache()->forget($this->tokenKey());

                    continue;
                }
                if (! $response->successful() || $response->json('status') !== 'waiting' || ! is_string($response->json('id')) || $response->json('id') === '') {
                    throw new SmsDeliveryException;
                }

                return;
            }
        } catch (ConnectionException|LockTimeoutException) {
            throw new SmsDeliveryException;
        }

        throw new SmsDeliveryException;
    }

    private function accessToken(): string
    {
        return $this->cache()->lock($this->tokenKey().':lock', 15)->block(2, function () {
            return $this->cache()->remember($this->tokenKey(), config('sms.eskiz.token_cache_seconds'), function () {
                $response = Http::asMultipart()->acceptJson()
                    ->timeout(config('sms.eskiz.timeout'))->connectTimeout(config('sms.eskiz.connect_timeout'))
                    ->post(config('sms.eskiz.base_url').'/auth/login', [
                        'email' => config('sms.eskiz.email'),
                        'password' => config('sms.eskiz.password'),
                    ]);
                $token = $response->json('data.token');
                if (! $response->successful() || ! is_string($token) || $token === '') {
                    throw new SmsDeliveryException;
                }

                return $token;
            });
        });
    }

    private function tokenKey(): string
    {
        return 'eskiz:token:'.hash('sha256', config('sms.eskiz.base_url').'|'.config('sms.eskiz.email'));
    }

    private function cache()
    {
        return Cache::store(config('sms.cache_store'));
    }
}
