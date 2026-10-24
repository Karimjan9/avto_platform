<?php

namespace App\Services;

use App\Exceptions\SmsDeliveryException;
use App\Models\PhoneLoginChallenge;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class PhoneOtpService
{
    public function __construct(private EskizSmsGateway $gateway) {}

    public static function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if (! preg_match('/^\+?[0-9\s().-]+$/D', $phone)) {
            throw new InvalidArgumentException('Invalid phone number.');
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 9 && ! str_starts_with($phone, '+')) {
            $digits = '998'.$digits;
        }
        if (! preg_match('/^998[0-9]{9}$/D', $digits)) {
            throw new InvalidArgumentException('Invalid phone number.');
        }

        return '+'.$digits;
    }

    public function issue(string $phone, string $binding, string $ip, ?int $boundUserId = null): PhoneLoginChallenge
    {
        if (! config('sms.enabled')) {
            throw new SmsDeliveryException;
        }
        $phone = self::normalizePhone($phone);

        $ipHash = $this->digest('ip:'.$ip);

        return $this->withLock('ip:'.$ipHash, fn () => $this->withPhoneLock($phone, function () use ($phone, $binding, $ipHash, $boundUserId) {
            $this->assertPhoneAvailable($phone, $boundUserId);
            $this->assertSendLimits($phone, $ipHash);
            $id = (string) Str::uuid();
            $code = (string) random_int(100000, 999999);
            $challenge = DB::transaction(function () use ($phone, $binding, $ipHash, $boundUserId, $id, $code) {
                // Even a failed resend cannot reactivate its predecessor.
                PhoneLoginChallenge::where('phone', $phone)->whereNull('consumed_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

                return PhoneLoginChallenge::create([
                    'id' => $id, 'phone' => $phone, 'bound_user_id' => $boundUserId,
                    'session_hash' => $this->sessionHash($id, $binding), 'ip_hash' => $ipHash,
                    'code_hash' => Hash::make($this->codeDigest($id, $code)),
                    'expires_at' => now()->addSeconds(config('sms.otp.expires_seconds')),
                ]);
            });
            try {
                $this->gateway->send($phone, str_replace(':code', $code, config('sms.message')));
            } catch (SmsDeliveryException $exception) {
                $challenge->update(['failed_at' => now(), 'revoked_at' => now()]);

                throw $exception;
            }
            $challenge->update([
                'sent_at' => now(),
                'expires_at' => now()->addSeconds(config('sms.otp.expires_seconds')),
            ]);

            return $challenge->refresh();
        }));
    }

    public function pending(?string $id, ?string $binding, ?int $boundUserId = null): ?PhoneLoginChallenge
    {
        if (! $id || ! $binding) {
            return null;
        }
        $challenge = PhoneLoginChallenge::find($id);
        if (! $challenge || ! hash_equals($challenge->session_hash, $this->sessionHash($id, $binding)) || $challenge->bound_user_id !== $boundUserId || ! $challenge->sent_at || $challenge->failed_at || $challenge->consumed_at) {
            return null;
        }

        return $challenge;
    }

    public function verify(string $id, string $code, string $binding, ?int $boundUserId = null): User
    {
        $pending = $this->pending($id, $binding, $boundUserId);
        if (! $pending) {
            $this->invalidCode();
        }

        $result = $this->withPhoneLock($pending->phone, function () use ($id, $code, $binding, $boundUserId) {
            return DB::transaction(function () use ($id, $code, $binding, $boundUserId) {
                $challenge = PhoneLoginChallenge::whereKey($id)->lockForUpdate()->first();
                if (! $challenge || ! hash_equals($challenge->session_hash, $this->sessionHash($id, $binding)) || $challenge->bound_user_id !== $boundUserId || ! $challenge->sent_at || $challenge->failed_at || $challenge->consumed_at || $challenge->revoked_at || $challenge->expires_at->isPast() || $challenge->attempts >= config('sms.otp.max_attempts')) {
                    return 'code';
                }
                if (! preg_match('/^[0-9]{6}$/D', $code) || ! Hash::check($this->codeDigest($id, $code), $challenge->code_hash)) {
                    $attempts = $challenge->attempts + 1;
                    $challenge->update(['attempts' => $attempts, 'revoked_at' => $attempts >= config('sms.otp.max_attempts') ? now() : null]);

                    return 'code';
                }

                // Consume before resolving identity; conflicts also burn the code.
                $consumed = PhoneLoginChallenge::whereKey($id)->whereNull('consumed_at')->whereNull('revoked_at')
                    ->where('attempts', '<', config('sms.otp.max_attempts'))->where('expires_at', '>', now())
                    ->update(['consumed_at' => now()]);
                if ($consumed !== 1) {
                    return 'code';
                }

                $owner = User::where('phone', $challenge->phone)->lockForUpdate()->first();
                if ($boundUserId !== null) {
                    $user = User::whereKey($boundUserId)->lockForUpdate()->first();
                    if (! $user || ($owner && $owner->id !== $user->id)) {
                        return 'phone';
                    }
                    // A nested transaction gives unique-phone races a savepoint.
                    try {
                        DB::transaction(fn () => $user->forceFill(['phone' => $challenge->phone, 'phone_verified_at' => now()])->save());
                    } catch (UniqueConstraintViolationException) {
                        return 'phone';
                    }

                    return $user->refresh();
                }

                if ($owner) {
                    return $owner->phone_verified_at ? $owner : 'phone';
                }
                $user = User::unguarded(fn () => User::query()->createOrFirst(['phone' => $challenge->phone], [
                    'name' => 'Foydalanuvchi', 'email' => null,
                    'password' => Str::random(64), 'phone_verified_at' => now(),
                ]));

                return $user->phone_verified_at ? $user->refresh() : 'phone';
            }, 3);
        });
        if ($result === 'phone') {
            throw ValidationException::withMessages(['phone' => 'Bu telefon hisobga bog‘langan. Hisobingizga email orqali kiring yoki yordam xizmatiga murojaat qiling.']);
        }
        if (! $result instanceof User) {
            $this->invalidCode();
        }

        return $result;
    }

    private function assertPhoneAvailable(string $phone, ?int $boundUserId): void
    {
        $owner = User::where('phone', $phone)->first();
        if ($owner && (($boundUserId !== null && $owner->id !== $boundUserId) || ($boundUserId === null && ! $owner->phone_verified_at))) {
            throw ValidationException::withMessages(['phone' => 'Bu telefon hisobga bog‘langan. Hisobingizga email orqali kiring yoki yordam xizmatiga murojaat qiling.']);
        }
    }

    private function assertSendLimits(string $phone, string $ipHash): void
    {
        $status = $this->sendStatusForHash($phone, $ipHash);
        if ($status['retryAfter'] > 0) {
            throw new TooManyRequestsHttpException($status['retryAfter'], $status['limitMessage'] ?? 'Biroz kuting. Yangi kodni 60 soniyadan keyin so‘rashingiz mumkin.', null, 0, [
                'X-RateLimit-Reset' => $status['retryAvailableAt']->getTimestamp(),
            ]);
        }
    }

    public function sendStatus(string $phone, string $ip): array
    {
        return $this->sendStatusForHash(self::normalizePhone($phone), $this->digest('ip:'.$ip));
    }

    private function sendStatusForHash(string $phone, string $ipHash): array
    {
        $now = CarbonImmutable::now();
        $availableAt = $now;
        $phoneBlockedUntil = null;
        $retryReason = null;
        $limitMessage = null;
        $sentCount = 0;
        foreach ([
            ['phone', $phone, 'sent_at', 3600, 'phone_hourly_limit'],
            ['phone', $phone, 'sent_at', 86400, 'phone_daily_limit'],
            ['ip_hash', $ipHash, 'sent_at', 3600, 'ip_hourly_limit'],
            ['ip_hash', $ipHash, 'sent_at', 86400, 'ip_daily_limit'],
            ['phone', $phone, 'created_at', 3600, 'phone_attempt_hourly_limit'],
            ['ip_hash', $ipHash, 'created_at', 3600, 'ip_attempt_hourly_limit'],
        ] as [$column, $value, $timeColumn, $seconds, $limit]) {
            $maximum = (int) config('sms.otp.'.$limit);
            $recent = PhoneLoginChallenge::where($column, $value)->where($timeColumn, '>', $now->subSeconds($seconds))
                ->latest($timeColumn)->limit($maximum)->get([$timeColumn]);
            if ($limit === 'phone_hourly_limit') {
                $sentCount = $recent->count();
                if ($recent->isNotEmpty()) {
                    $cooldown = $recent->first()->sent_at->addSeconds(config('sms.otp.resend_seconds'));
                    if ($cooldown->isAfter($availableAt)) {
                        $availableAt = $cooldown;
                    }
                }
            }
            if ($recent->count() < $maximum) {
                continue;
            }
            // The Nth most recent timestamp is when this rolling quota frees a slot.
            $until = $recent->last()->getAttribute($timeColumn)->toImmutable()->addSeconds($seconds);
            if ($column === 'phone' && ($phoneBlockedUntil === null || $until->isAfter($phoneBlockedUntil))) {
                $phoneBlockedUntil = $until;
            }
            if ($until->isAfter($availableAt)) {
                $availableAt = $until;
                $retryReason = $column === 'phone' ? 'phone' : 'ip';
                $limitMessage = $limit === 'phone_hourly_limit'
                    ? 'Bir soatda faqat '.config('sms.otp.phone_hourly_limit').' ta SMS olish mumkin. Biroz kuting va qayta urinib ko‘ring.'
                    : 'Juda ko‘p urinish. Biroz kuting va qayta urinib ko‘ring.';
            }
        }

        return [
            'sentCount' => $sentCount,
            'maxSends' => (int) config('sms.otp.phone_hourly_limit'),
            'phoneBlockedUntil' => $phoneBlockedUntil,
            'retryAvailableAt' => $availableAt,
            'resendAvailableAt' => $availableAt,
            'retryReason' => $retryReason,
            'limitMessage' => $limitMessage,
            'retryAfter' => max(0, (int) ceil($now->diffInSeconds($availableAt, false))),
        ];
    }

    private function withPhoneLock(string $phone, Closure $callback): mixed
    {
        return $this->withLock($this->digest($phone), $callback);
    }

    private function withLock(string $key, Closure $callback): mixed
    {
        try {
            return Cache::store(config('sms.cache_store'))->lock('phone-otp:'.$key, 90)->block(2, $callback);
        } catch (LockTimeoutException) {
            throw new TooManyRequestsHttpException(3, 'So‘rov bajarilmoqda. Biroz kuting.');
        }
    }

    private function sessionHash(string $id, string $binding): string
    {
        return $this->digest('session:'.$id.':'.$binding);
    }

    private function codeDigest(string $id, string $code): string
    {
        return $this->digest('code:'.$id.':'.$code);
    }

    private function digest(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function invalidCode(): never
    {
        throw ValidationException::withMessages(['code' => 'Kod noto‘g‘ri yoki muddati tugagan. Yangi kod so‘rang.']);
    }
}
