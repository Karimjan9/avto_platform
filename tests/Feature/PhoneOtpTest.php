<?php

namespace Tests\Feature;

use App\Exceptions\SmsDeliveryException;
use App\Models\PhoneLoginChallenge;
use App\Models\User;
use App\Services\EskizSmsGateway;
use App\Services\PhoneOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class PhoneOtpTest extends TestCase
{
    use RefreshDatabase;

    private array $codes = [];

    private int $smsAttempts = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sms.enabled' => true, 'sms.cache_store' => 'array',
            'sms.eskiz.email' => 'test@example.invalid', 'sms.eskiz.password' => 'fake-password',
        ]);
        Cache::store('array')->flush();
        Http::preventStrayRequests();
        $this->fakeGateway();
    }

    private function fakeGateway(int $sendStatus = 200): void
    {
        $this->resetHttp();
        Http::fake(function (Request $request) use ($sendStatus) {
            if (str_ends_with($request->url(), '/auth/login')) {
                return Http::response(['data' => ['token' => 'fake-token']], 200);
            }
            if (str_ends_with($request->url(), '/message/sms/send')) {
                $this->smsAttempts++;
                $data = $this->requestData($request);
                preg_match('/kirish kodingiz: ([0-9]{6})\./', $data['message'], $match);
                $this->codes['+'.$data['mobile_phone']] = $match[1] ?? '';

                return Http::response($sendStatus === 200 ? ['id' => 'fake-message-id', 'status' => 'waiting'] : ['message' => 'private provider error'], $sendStatus);
            }

            throw new \RuntimeException('Unexpected HTTP request.');
        });
    }

    private function resetHttp(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
    }

    private function requestData(Request $request): array
    {
        return $request->isMultipart() ? collect($request->data())->mapWithKeys(fn ($part) => [$part['name'] => $part['contents']])->all() : $request->data();
    }

    private function otp(): PhoneOtpService
    {
        return app(PhoneOtpService::class);
    }

    private function rejectCode(callable $callback, string $field = 'code'): void
    {
        try {
            $callback();
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_phone_normalization_accepts_local_and_formatted_uzbek_numbers_only(): void
    {
        foreach (['90 123-45-67', '+998 (90) 123 45 67', '998901234567'] as $phone) {
            $this->assertSame('+998901234567', PhoneOtpService::normalizePhone($phone));
        }
        foreach (['+7901234567', '+901234567', '+998123456', '99890123456', '+99890abc4567', '++998901234567', ''] as $phone) {
            try {
                PhoneOtpService::normalizePhone($phone);
                $this->fail('Malformed phone accepted.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_code_is_hashed_session_bound_and_single_use_and_creates_phone_only_account(): void
    {
        $challenge = $this->otp()->issue('+998901234567', 'session-secret', '192.0.2.1');
        $code = $this->codes[$challenge->phone];
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
        $this->assertFalse(Hash::check($code, $challenge->code_hash));
        $this->assertStringNotContainsString($code, json_encode($challenge->getAttributes()));
        $this->assertStringNotContainsString('session-secret', json_encode($challenge->getAttributes()));
        $this->assertArrayNotHasKey('code_hash', $challenge->toArray());
        $this->assertNull($this->otp()->pending($challenge->id, 'another-session'));
        $this->rejectCode(fn () => $this->otp()->verify($challenge->id, $code, 'another-session'));
        $user = $this->otp()->verify($challenge->id, $code, 'session-secret');
        $this->assertTrue($user->hasVerifiedPhone());
        $this->assertNull($user->email);
        $this->assertFalse($user->is_admin);
        $this->assertNull($user->seller_onboarded_at);
        $this->assertNotNull($challenge->refresh()->consumed_at);
        $this->rejectCode(fn () => $this->otp()->verify($challenge->id, $code, 'session-secret'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_five_wrong_attempts_persist_and_burn_even_the_correct_code(): void
    {
        $challenge = $this->otp()->issue('+998901234567', 'session', '192.0.2.1');
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->rejectCode(fn () => $this->otp()->verify($challenge->id, '000000', 'session'));
            $this->assertSame($attempt, $challenge->refresh()->attempts);
        }
        $this->assertNotNull($challenge->revoked_at);
        $this->rejectCode(fn () => $this->otp()->verify($challenge->id, $this->codes[$challenge->phone], 'session'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_code_cannot_create_an_account(): void
    {
        $challenge = $this->otp()->issue('+998901234567', 'session', '192.0.2.1');
        $this->travel(301)->seconds();
        $this->rejectCode(fn () => $this->otp()->verify($challenge->id, $this->codes[$challenge->phone], 'session'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_resend_cooldown_is_global_across_sessions_and_new_code_revokes_old_challenge(): void
    {
        $first = $this->otp()->issue('+998901234567', 'first', '192.0.2.1');
        $oldCode = $this->codes[$first->phone];
        try {
            $this->otp()->issue($first->phone, 'second', '192.0.2.2');
            $this->fail('Cooldown bypassed.');
        } catch (TooManyRequestsHttpException $exception) {
            $this->assertGreaterThan(0, $exception->getHeaders()['Retry-After']);
        }
        $this->assertDatabaseCount('phone_login_challenges', 1);
        $this->travel(61)->seconds();
        $second = $this->otp()->issue($first->phone, 'second', '192.0.2.2');
        $this->assertNotNull($first->refresh()->revoked_at);
        $this->rejectCode(fn () => $this->otp()->verify($first->id, $oldCode, 'first'));
        $this->assertTrue($this->otp()->verify($second->id, $this->codes[$second->phone], 'second')->hasVerifiedPhone());
    }

    public function test_failed_resend_is_unverifiable_and_does_not_count_as_success_or_revive_old_code(): void
    {
        $first = $this->otp()->issue('+998901234567', 'first', '192.0.2.1');
        $oldCode = $this->codes[$first->phone];
        $this->travel(61)->seconds();
        $this->fakeGateway(500);
        try {
            $this->otp()->issue($first->phone, 'second', '192.0.2.1');
            $this->fail('Provider failure accepted.');
        } catch (SmsDeliveryException $exception) {
            $this->assertStringNotContainsString('private provider', $exception->getMessage());
        }
        $failed = PhoneLoginChallenge::whereNotNull('failed_at')->firstOrFail();
        $this->assertNull($failed->sent_at);
        $this->assertNotNull($failed->revoked_at);
        $this->assertSame(1, PhoneLoginChallenge::whereNotNull('sent_at')->count());
        $this->rejectCode(fn () => $this->otp()->verify($first->id, $oldCode, 'first'));
        $this->rejectCode(fn () => $this->otp()->verify($failed->id, $this->codes[$failed->phone], 'second'));
        $this->fakeGateway();
        $this->assertNotNull($this->otp()->issue($first->phone, 'third', '192.0.2.1')->sent_at);
    }

    public function test_successful_send_limits_apply_per_phone_across_ips(): void
    {
        $this->travelTo(now()->startOfSecond());
        $started = now()->toImmutable();
        for ($index = 0; $index < 3; $index++) {
            $challenge = $this->otp()->issue('+998901234567', 'session-'.$index, '192.0.2.'.($index + 1));
            if ($index < 2) {
                $this->travel(60)->seconds();
            }
        }
        $this->assertSame(3, $this->smsAttempts);
        try {
            $this->otp()->issue('+998901234567', 'new-session', '192.0.2.4');
            $this->fail('Fourth send bypassed the phone limit.');
        } catch (TooManyRequestsHttpException $exception) {
            $this->assertSame(3480, (int) $exception->getHeaders()['Retry-After']);
            $this->assertSame($started->addHour()->getTimestamp(), $exception->getHeaders()['X-RateLimit-Reset']);
        }
        $this->assertSame(3, $this->smsAttempts);
        $this->assertDatabaseCount('phone_login_challenges', 3);
        // The third code can still be consumed while further sends are blocked.
        $this->assertTrue($this->otp()->verify($challenge->id, $this->codes[$challenge->phone], 'session-2')->hasVerifiedPhone());
    }

    public function test_successful_send_limits_apply_per_ip_across_phones(): void
    {
        config(['sms.otp.ip_hourly_limit' => 2]);
        $this->otp()->issue('+998901234561', 'first', '192.0.2.1');
        $this->otp()->issue('+998901234562', 'second', '192.0.2.1');
        $this->expectException(TooManyRequestsHttpException::class);
        $this->otp()->issue('+998901234563', 'third', '192.0.2.1');
    }

    public function test_provider_failures_have_separate_attempt_limits(): void
    {
        $this->fakeGateway(500);
        config(['sms.otp.phone_attempt_hourly_limit' => 2]);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $this->otp()->issue('+998901234567', 'session', '192.0.2.1');
                $this->fail('Provider failure accepted.');
            } catch (SmsDeliveryException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, PhoneLoginChallenge::whereNotNull('sent_at')->count());
        $this->expectException(TooManyRequestsHttpException::class);
        $this->otp()->issue('+998901234567', 'session', '192.0.2.1');
    }

    public function test_guest_cannot_take_over_email_account_with_unverified_phone(): void
    {
        $owner = User::factory()->unverifiedPhone()->create(['phone' => '+998901234567']);
        $this->rejectCode(fn () => $this->otp()->issue($owner->phone, 'session', '192.0.2.1'), 'phone');
        Http::assertNothingSent();
        $this->assertDatabaseCount('phone_login_challenges', 0);
        $this->assertNull($owner->refresh()->phone_verified_at);
    }

    public function test_existing_verified_phone_logs_in_same_account_without_linking_by_email(): void
    {
        $owner = User::factory()->create(['phone' => '+998901234567']);
        $owner->forceFill(['phone_verified_at' => now()])->save();
        $challenge = $this->otp()->issue($owner->phone, 'session', '192.0.2.1');
        $this->assertSame($owner->id, $this->otp()->verify($challenge->id, $this->codes[$owner->phone], 'session')->id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_authenticated_email_user_can_verify_own_phone_preserving_identity(): void
    {
        $owner = User::factory()->unverifiedPhone()->create(['phone' => '+998901234567']);
        $password = $owner->password;
        $challenge = $this->otp()->issue($owner->phone, 'session', '192.0.2.1', $owner->id);
        $this->rejectCode(fn () => $this->otp()->verify($challenge->id, $this->codes[$owner->phone], 'session'));
        $user = $this->otp()->verify($challenge->id, $this->codes[$owner->phone], 'session', $owner->id);
        $this->assertSame($owner->id, $user->id);
        $this->assertSame($owner->email, $user->email);
        $this->assertSame($password, $user->password);
        $this->assertTrue($user->hasVerifiedPhone());
    }

    public function test_authenticated_user_can_link_free_phone_but_not_phone_owned_by_someone_else(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->unverifiedPhone()->create(['phone' => '+998901234568']);
        $this->rejectCode(fn () => $this->otp()->issue($other->phone, 'session', '192.0.2.1', $owner->id), 'phone');
        $challenge = $this->otp()->issue('+998901234567', 'session', '192.0.2.1', $owner->id);
        $this->rejectCode(fn () => $this->otp()->verify($challenge->id, $this->codes[$challenge->phone], 'session', $other->id));
        $user = $this->otp()->verify($challenge->id, $this->codes[$challenge->phone], 'session', $owner->id);
        $this->assertSame($owner->id, $user->id);
        $this->assertSame('+998901234567', $user->phone);
        $this->assertTrue($user->hasVerifiedPhone());
        $this->assertNull($other->refresh()->phone_verified_at);
    }

    public function test_phone_claimed_after_send_causes_conflict_and_consumes_code_without_account_merge(): void
    {
        $challenge = $this->otp()->issue('+998901234567', 'session', '192.0.2.1');
        $owner = User::factory()->unverifiedPhone()->create(['phone' => $challenge->phone]);
        $this->rejectCode(fn () => $this->otp()->verify($challenge->id, $this->codes[$challenge->phone], 'session'), 'phone');
        $this->assertNotNull($challenge->refresh()->consumed_at);
        $this->assertNull($owner->refresh()->phone_verified_at);
        $this->assertDatabaseCount('users', 1);
        $this->rejectCode(fn () => $this->otp()->verify($challenge->id, $this->codes[$challenge->phone], 'session'));
    }

    public function test_eskiz_token_is_cached_and_explicit_401_refreshes_once_without_retrying_other_errors(): void
    {
        $this->resetHttp();
        Http::fake([
            '*/auth/login' => Http::sequence()->push(['data' => ['token' => 'old-token']])->push(['data' => ['token' => 'new-token']]),
            '*/message/sms/send' => Http::sequence()->push(['message' => 'expired'], 401)->push(['id' => 'accepted-id', 'status' => 'waiting'])->push(['message' => 'error'], 500),
        ]);
        $gateway = app(EskizSmsGateway::class);
        $gateway->send('+998901234567', 'Test message');
        try {
            $gateway->send('+998901234568', 'Other message');
            $this->fail('Provider500 accepted.');
        } catch (SmsDeliveryException) {
            $this->addToAssertionCount(1);
        }
        Http::assertSentCount(5);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/message/sms/send') && $this->requestData($request)['mobile_phone'] === '998901234567' && $this->requestData($request)['from'] === '4546' && $request->hasHeader('Authorization', 'Bearer new-token'));
    }

    public function test_eskiz_rejects_malformed_acceptance_and_disabled_gateway_sends_nothing(): void
    {
        $this->resetHttp();
        Http::fake([
            '*/auth/login' => Http::response(['data' => ['token' => 'token']]),
            '*/message/sms/send' => Http::response(['status' => 'error', 'message' => 'private details']),
        ]);
        try {
            app(EskizSmsGateway::class)->send('+998901234567', 'message');
            $this->fail('Malformed provider acceptance accepted.');
        } catch (SmsDeliveryException $exception) {
            $this->assertStringNotContainsString('private details', $exception->getMessage());
        }
        config(['sms.enabled' => false]);
        $this->resetHttp();
        Http::fake();
        $this->expectException(SmsDeliveryException::class);
        try {
            app(EskizSmsGateway::class)->send('+998901234567', 'message');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_database_cache_store_supports_challenge_locks_and_cached_eskiz_tokens(): void
    {
        config(['sms.cache_store' => 'database']);
        $challenge = $this->otp()->issue('+998901234567', 'session', '192.0.2.1');
        $this->assertTrue($this->otp()->verify($challenge->id, $this->codes[$challenge->phone], 'session')->hasVerifiedPhone());
        $this->assertDatabaseCount('cache_locks', 0);
        $this->assertDatabaseCount('cache', 1);
    }

    public function test_eskiz_repeated_401_stops_after_one_refresh_and_connection_failure_is_not_retried(): void
    {
        $this->resetHttp();
        Http::fake([
            '*/auth/login' => Http::response(['data' => ['token' => 'token']]),
            '*/message/sms/send' => Http::response(['message' => 'private body'], 401),
        ]);
        try {
            app(EskizSmsGateway::class)->send('+998901234567', 'message');
            $this->fail('Repeated401 accepted.');
        } catch (SmsDeliveryException) {
            Http::assertSentCount(4);
        }
        Cache::store('array')->flush();
        $this->resetHttp();
        Http::fake([
            '*/auth/login' => Http::response(['data' => ['token' => 'token']]),
            '*/message/sms/send' => Http::failedConnection(),
        ]);
        try {
            app(EskizSmsGateway::class)->send('+998901234567', 'message');
            $this->fail('Connection failure accepted.');
        } catch (SmsDeliveryException $exception) {
            $this->assertStringNotContainsString('cURL', $exception->getMessage());
            Http::assertSentCount(2); // One auth request and one failed send; no retry.
        }
    }

    public function test_web_phone_login_keeps_posting_destination_until_first_seller_onboarding(): void
    {
        $intended = route('cabinet.listing.create').'?section=parts&condition=new';
        $this->withSession(['url.intended' => $intended])->post(route('phone.send'), ['phone' => '90 123 45 67'])
            ->assertRedirect(route('phone.verify'))->assertSessionHas('phone_auth.challenge')->assertSessionHas('phone_auth.secret');
        $this->assertGuest();
        $session = $this->app['session.store'];
        $this->assertArrayNotHasKey('code', $session->get('phone_auth'));
        $code = $this->codes['+998901234567'];
        $this->assertStringNotContainsString($code, json_encode($session->get('phone_auth')));
        $this->post(route('phone.verify.store'), ['code' => $code])->assertRedirect(route('seller.onboarding'))
            ->assertSessionMissing('phone_auth')->assertSessionHas('url.intended', $intended);
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->hasVerifiedPhone());
        $this->assertFalse(auth()->user()->hasCompletedSellerProfile());
    }

    public function test_wrong_web_code_is_not_flashed_into_session_as_old_input(): void
    {
        $this->post(route('phone.send'), ['phone' => '+998901234567'])->assertRedirect(route('phone.verify'));
        $this->from(route('phone.verify'))->post(route('phone.verify.store'), ['code' => '000000'])
            ->assertRedirect(route('phone.verify'))->assertSessionHasErrors('code');
        $this->assertArrayNotHasKey('code', $this->app['session.store']->get('_old_input', []));
        $this->assertGuest();
    }

    public function test_failed_web_sms_returns_safe_502_without_code_provider_details_or_usable_session(): void
    {
        $this->fakeGateway(500);
        $response = $this->postJson(route('phone.send'), ['phone' => '+998901234567'])->assertStatus(502);
        $response->assertSessionMissing('phone_auth')->assertJsonStructure(['message']);
        $this->assertStringNotContainsString($this->codes['+998901234567'], $response->getContent());
        $this->assertStringNotContainsString('private provider', $response->getContent());
        $this->assertStringNotContainsString('fake-password', $response->getContent());
        $this->assertDatabaseMissing('phone_login_challenges', ['failed_at' => null]);
        $this->assertGuest();
    }

    public function test_authenticated_web_user_verifies_phone_without_changing_account_and_returns_to_posting(): void
    {
        $user = User::factory()->unverifiedPhone()->create(['phone' => '+998901234567']);
        $this->actingAs($user)->withSession(['url.intended' => route('cabinet.listing.create')]);
        $this->post(route('phone.send'), ['phone' => $user->phone])->assertRedirect(route('phone.verify'));
        $this->post(route('phone.verify.store'), ['code' => $this->codes[$user->phone]])
            ->assertRedirect(route('cabinet.listing.create'))->assertSessionMissing('phone_auth');
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->refresh()->hasVerifiedPhone());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_web_input_validation_rejects_non_uzbek_or_structured_phones_without_sending_sms(): void
    {
        foreach ([['bad'], '+7901234567', 'not a phone'] as $phone) {
            $this->postJson(route('phone.send'), ['phone' => $phone])->assertUnprocessable()->assertJsonValidationErrors('phone');
        }
        $this->postJson(route('phone.verify.store'), ['code' => ['123456']])->assertUnprocessable()->assertJsonValidationErrors('code');
        Http::assertNothingSent();
        $this->assertDatabaseCount('phone_login_challenges', 0);
    }

    public function test_auth_pages_render_real_challenge_timing_and_never_render_the_plain_code(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->get(route('phone.start'))->assertOk()->assertSee('data-phone-auth-form', false);
        $this->post(route('phone.send'), ['phone' => '+998901234567'])->assertRedirect(route('phone.verify'));
        $response = $this->get(route('phone.verify'))->assertOk()->assertSee('data-otp-input', false)->assertSee('SMS kodni kiriting');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString($this->codes['+998901234567'], $response->getContent());
        $response->assertViewHas('expiresAt', fn ($time) => $time->equalTo(now()->addSeconds(300)));
        $response->assertViewHas('resendAvailableAt', fn ($time) => $time->equalTo(now()->addSeconds(60)));
    }

    public function test_html_provider_failure_renders_retry_form_with_safe_error_and_no_cache(): void
    {
        $this->fakeGateway(500);
        $response = $this->post(route('phone.send'), ['phone' => '+998901234567'])->assertStatus(502)->assertSee('SMS yuborilmadi.');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('private provider', $response->getContent());
        $this->assertStringNotContainsString($this->codes['+998901234567'], $response->getContent());
        $response->assertSessionMissing('phone_auth');
    }

    public function test_third_web_send_is_allowed_but_fourth_initial_or_resend_request_is_inline_limited(): void
    {
        $this->travelTo(now()->startOfSecond());
        $started = now()->toImmutable();
        $phone = '+998901234567';
        $this->post(route('phone.send'), ['phone' => $phone])->assertRedirect(route('phone.verify'));
        $this->travel(60)->seconds();
        $this->post(route('phone.resend'))->assertRedirect(route('phone.verify'));
        $this->travel(60)->seconds();
        $this->post(route('phone.resend'))->assertRedirect(route('phone.verify'));
        $challengeId = session('phone_auth.challenge');
        $this->get(route('phone.verify'))->assertOk()->assertViewHas('sentCount', 3)->assertViewHas('maxSends', 3)
            ->assertViewHas('phoneBlockedUntil', fn ($time) => $time->equalTo($started->addHour()))
            ->assertViewHas('resendAvailableAt', fn ($time) => $time->equalTo($started->addHour()));
        $response = $this->post(route('phone.resend'))->assertStatus(429)->assertViewIs('auth.phone-verify')
            ->assertHeader('Retry-After', '3480')->assertSessionHas('phone_auth.challenge', $challengeId)
            ->assertViewHas('sentCount', 3)->assertViewHas('retryReason', 'phone');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString($this->codes[$phone], $response->getContent());
        $this->withSession(['phone_auth' => null])->postJson(route('phone.send'), ['phone' => $phone])
            ->assertStatus(429)->assertHeader('Retry-After', '3480')->assertJsonPath('sent_count', 3)->assertJsonPath('retry_reason', 'phone');
        $this->assertSame(3, $this->smsAttempts);
        $this->assertDatabaseCount('phone_login_challenges', 3);
    }

    public function test_hour_quota_releases_one_slot_at_a_time_in_the_rolling_window(): void
    {
        $this->travelTo(now()->startOfSecond());
        $started = now()->toImmutable();
        $phone = '+998901234567';
        $this->otp()->issue($phone, 'first', '192.0.2.1');
        $this->travel(1200)->seconds();
        $this->otp()->issue($phone, 'second', '192.0.2.1');
        $this->travel(1200)->seconds();
        $this->otp()->issue($phone, 'third', '192.0.2.1');
        $this->assertSame(1200, $this->otp()->sendStatus($phone, '192.0.2.1')['retryAfter']);
        $this->travelTo($started->addSeconds(3599));
        try {
            $this->otp()->issue($phone, 'early', '192.0.2.1');
            $this->fail('Hour boundary was released early.');
        } catch (TooManyRequestsHttpException $exception) {
            $this->assertSame(1, (int) $exception->getHeaders()['Retry-After']);
        }
        $this->travel(1)->seconds();
        $this->otp()->issue($phone, 'fourth', '192.0.2.1');
        $this->assertSame(4, $this->smsAttempts);
        $this->travel(60)->seconds();
        try {
            $this->otp()->issue($phone, 'fifth', '192.0.2.1');
            $this->fail('Rolling hour was reset as a whole.');
        } catch (TooManyRequestsHttpException $exception) {
            $this->assertSame(1140, (int) $exception->getHeaders()['Retry-After']);
        }
        $this->assertSame(4, $this->smsAttempts);
    }

    public function test_invalid_and_failed_provider_requests_do_not_use_successful_sms_quota(): void
    {
        $this->travelTo(now()->startOfSecond());
        $phone = '+998901234567';
        $this->postJson(route('phone.send'), ['phone' => 'invalid'])->assertUnprocessable();
        $this->assertSame(0, $this->smsAttempts);
        $this->otp()->issue($phone, 'first', '192.0.2.1');
        $this->travel(60)->seconds();
        $this->fakeGateway(500);
        try {
            $this->otp()->issue($phone, 'failed', '192.0.2.1');
            $this->fail('Provider failure accepted.');
        } catch (SmsDeliveryException) {
            $this->assertSame(1, $this->otp()->sendStatus($phone, '192.0.2.1')['sentCount']);
        }
        $this->fakeGateway();
        $this->otp()->issue($phone, 'second', '192.0.2.1');
        $this->travel(60)->seconds();
        $this->otp()->issue($phone, 'third', '192.0.2.1');
        $this->assertSame(3, PhoneLoginChallenge::whereNotNull('sent_at')->count());
        $this->assertSame(1, PhoneLoginChallenge::whereNotNull('failed_at')->count());
        $this->assertSame(4, $this->smsAttempts);
        $this->assertSame(3, $this->otp()->sendStatus($phone, '192.0.2.1')['sentCount']);
    }

    public function test_sms_disabled_returns_clear_503_without_creating_challenge_or_calling_provider(): void
    {
        config(['sms.enabled' => false]);
        $this->get(route('phone.start'))->assertOk();
        $this->post(route('phone.send'), ['phone' => '+998901234567'])->assertStatus(503)->assertViewIs('auth.phone-start')
            ->assertViewHas('errors', fn ($errors) => $errors->has('phone'));
        $this->postJson(route('phone.send'), ['phone' => '+998901234567'])->assertStatus(503)->assertJsonPath('code', 'sms_unavailable');
        $this->assertSame(0, $this->smsAttempts);
        $this->assertDatabaseCount('phone_login_challenges', 0);
        Http::assertNothingSent();
    }

    public function test_route_throttle_keeps_otp_form_and_challenge_without_reflecting_or_flashing_code(): void
    {
        $this->travelTo(now()->startOfSecond());
        $phone = '+998901234567';
        $this->post(route('phone.send'), ['phone' => $phone])->assertRedirect(route('phone.verify'));
        $challengeId = session('phone_auth.challenge');
        for ($attempt = 0; $attempt < 9; $attempt++) {
            $this->from(route('phone.verify'))->post(route('phone.verify.store'), ['code' => '12345'])->assertRedirect(route('phone.verify'));
        }
        $code = $this->codes[$phone];
        $response = $this->post(route('phone.verify.store'), ['code' => $code])->assertStatus(429)->assertViewIs('auth.phone-verify')
            ->assertHeader('Retry-After', '60')->assertSessionHas('phone_auth.challenge', $challengeId)->assertViewHas('retryReason', 'ip');
        $this->assertStringNotContainsString($code, $response->getContent());
        $this->assertArrayNotHasKey('code', session('_old_input', []));
        $this->assertSame(0, PhoneLoginChallenge::findOrFail($challengeId)->attempts);
        $this->assertSame(1, $this->smsAttempts);
        $this->travel(61)->seconds();
        $this->post(route('phone.verify.store'), ['code' => $code])->assertRedirect(route('seller.onboarding'));
        $this->assertAuthenticated();
    }
}
