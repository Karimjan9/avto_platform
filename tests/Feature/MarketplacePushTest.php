<?php

namespace Tests\Feature;

use App\Jobs\SendMarketplacePush;
use App\Models\MarketplaceNotification;
use App\Models\PushDevice;
use App\Models\User;
use App\Services\FcmClient;
use App\Services\MarketplaceNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplacePushTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_tokens_are_encrypted_hidden_scoped_and_refreshable(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $token = str_repeat('private-device-token-', 5);
        $id = $this->postJson('/api/v1/me/push-devices', ['device_id' => 'android-1', 'platform' => 'android', 'token' => $token])->assertCreated()->assertJsonMissingPath('data.token')->assertJsonMissingPath('data.token_hash')->json('data.id');
        $this->assertSame($token, PushDevice::find($id)->token);
        $this->assertNotSame($token, PushDevice::find($id)->getRawOriginal('token'));
        $this->postJson('/api/v1/me/push-devices', ['device_id' => 'android-1', 'platform' => 'android', 'token' => $token.'2'])->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('push_devices', 1);
        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson('/api/v1/me/push-devices/'.$id)->assertForbidden();
    }

    public function test_fcm_oauth_signing_send_and_delivery_deduplication(): void
    {
        $opensslConfig = tempnam(sys_get_temp_dir(), 'avto-openssl-test-');
        file_put_contents($opensslConfig, "[req]\ndistinguished_name=dn\n[dn]\n");
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $opensslConfig];
        $privateKey = openssl_pkey_new($options);
        openssl_pkey_export($privateKey, $pem, null, $options);
        $path = tempnam(sys_get_temp_dir(), 'avto-fcm-test-');
        file_put_contents($path, json_encode(['client_email' => 'test@example.invalid', 'private_key' => $pem]));
        config(['firebase.enabled' => true, 'firebase.project_id' => 'test-project', 'firebase.credentials' => $path]);
        Http::preventStrayRequests();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-access-token', 'expires_in' => 3600]), 'fcm.googleapis.com/*' => Http::response(['name' => 'projects/test-project/messages/1'])]);
        try {
            $user = User::factory()->create();
            $device = PushDevice::create(['user_id' => $user->id, 'device_id' => 'test-device', 'platform' => 'android', 'token' => 'fake-registration-token', 'token_hash' => hash('sha256', 'fake-registration-token'), 'last_seen_at' => now()]);
            $notice = MarketplaceNotification::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'type' => 'saved_search', 'title' => 'Yangi e’lon', 'data' => ['listing_id' => 12], 'push_status' => 'pending']);
            $job = new SendMarketplacePush($notice->id);
            $job->handle(app(FcmClient::class));
            $job->handle(app(FcmClient::class));
            $this->assertSame('sent', $notice->fresh()->push_status);
            Http::assertSentCount(2);
            Http::assertSent(function ($request) use ($privateKey) {
                if ($request->url() !== 'https://oauth2.googleapis.com/token') {
                    return false;
                }
                [$header, $claims, $signature] = explode('.', $request['assertion']);
                $decode = fn ($value) => base64_decode(strtr($value, '-_', '+/'));
                $publicKey = openssl_pkey_get_details($privateKey)['key'];

                return openssl_verify($header.'.'.$claims, $decode($signature), $publicKey, OPENSSL_ALGO_SHA256) === 1 && json_decode($decode($claims), true)['scope'] === 'https://www.googleapis.com/auth/firebase.messaging';
            });
            Http::assertSent(fn ($request) => str_contains($request->url(), 'fcm.googleapis.com') && $request['message']['token'] === $device->token && $request['message']['data']['notification_id'] === $notice->id && ! isset($request['message']['data']['contact_phone']));
        } finally {
            unlink($path);
            unlink($opensslConfig);
        }
    }

    public function test_unregistered_device_is_disabled_and_internal_notice_survives(): void
    {
        config(['firebase.enabled' => true, 'firebase.project_id' => 'test-project', 'firebase.credentials' => 'test-no-file']);
        Cache::put('firebase-oauth:'.hash('sha256', 'test-no-file'), 'fake-access-token', 300);
        Http::preventStrayRequests();
        Http::fake(['fcm.googleapis.com/*' => Http::response(['error' => ['details' => [['errorCode' => 'UNREGISTERED']]]], 404)]);
        $user = User::factory()->create();
        $device = PushDevice::create(['user_id' => $user->id, 'device_id' => 'dead-device', 'platform' => 'ios', 'token' => 'no-longer-valid-token', 'token_hash' => hash('sha256', 'no-longer-valid-token'), 'last_seen_at' => now()]);
        $notice = MarketplaceNotification::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'type' => 'saved_search', 'title' => 'Yangi e’lon', 'data' => [], 'push_status' => 'pending']);
        (new SendMarketplacePush($notice->id))->handle(app(FcmClient::class));
        $this->assertFalse($device->fresh()->is_active);
        $this->assertNotNull($notice->fresh());
    }

    public function test_disabled_firebase_preserves_in_app_notifications_without_network_calls(): void
    {
        config(['firebase.enabled' => false]);
        Http::preventStrayRequests();
        Http::fake();
        $user = User::factory()->create();
        $notice = app(MarketplaceNotifier::class)->user($user->id, 'inquiry', 'Yangi murojaat', ['inquiry_id' => 1], 'dedup-1');
        app(MarketplaceNotifier::class)->user($user->id, 'inquiry', 'Yangi murojaat', ['inquiry_id' => 1], 'dedup-1');
        (new SendMarketplacePush($notice->id))->handle(app(FcmClient::class));
        $this->assertDatabaseCount('marketplace_notifications', 1);
        Http::assertNothingSent();
    }
}
