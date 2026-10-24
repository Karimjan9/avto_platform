<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\SmsDeliveryException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendPhoneCodeRequest;
use App\Http\Requests\VerifyPhoneCodeRequest;
use App\Models\PhoneLoginChallenge;
use App\Services\PhoneOtpService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class PhoneAuthController extends Controller
{
    public function __construct(private PhoneOtpService $otp) {}

    public function create(Request $request)
    {
        return view('auth.phone-start', ['phone' => $request->user()?->phone ?? ''] + $this->initialSendStatus());
    }

    public function send(SendPhoneCodeRequest $request)
    {
        return $this->sendChallenge($request, $request->validated('phone'));
    }

    public function show(Request $request)
    {
        $challenge = $this->pending($request);
        if (! $challenge) {
            return redirect()->route('phone.start')->withErrors(['phone' => 'Yangi SMS kod so‘rang.']);
        }

        return view('auth.phone-verify', $this->verificationData($request, $challenge));
    }

    private function verificationData(Request $request, PhoneLoginChallenge $challenge, ?array $status = null): array
    {
        return [
            'phone' => $challenge->phone,
            'maskedPhone' => '+998 ** *** '.substr($challenge->phone, -4, 2).' '.substr($challenge->phone, -2),
            'expiresAt' => $challenge->expires_at,
        ] + ($status ?? $this->otp->sendStatus($challenge->phone, (string) $request->ip()));
    }

    public function resend(Request $request)
    {
        $challenge = $this->pending($request);
        if (! $challenge) {
            throw ValidationException::withMessages(['phone' => 'Yangi SMS kod so‘rang.']);
        }

        return $this->sendChallenge($request, $challenge->phone);
    }

    public function verify(VerifyPhoneCodeRequest $request)
    {
        $user = $this->otp->verify(
            (string) $request->session()->get('phone_auth.challenge', ''),
            $request->validated('code'),
            (string) $request->session()->get('phone_auth.secret', ''),
            $request->user()?->id,
        );
        Auth::login($user);
        $request->session()->forget('phone_auth');
        $request->session()->regenerate();

        if (! $user->hasCompletedSellerProfile()) {
            return redirect()->route('seller.onboarding');
        }

        return redirect()->intended(route('cabinet'));
    }

    private function sendChallenge(Request $request, string $phone)
    {
        if (! config('sms.enabled')) {
            $message = 'SMS orqali kirish vaqtincha mavjud emas. Birozdan keyin qayta urinib ko‘ring yoki email va parol bilan kiring.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'code' => 'sms_unavailable'], 503)->header('Cache-Control', 'private, no-store, max-age=0');
            }
            $errors = (new ViewErrorBag)->put('default', new MessageBag(['phone' => [$message]]));

            return response()->view('auth.phone-start', ['phone' => $phone, 'errors' => $errors] + $this->initialSendStatus(), 503)->header('Cache-Control', 'private, no-store, max-age=0');
        }
        $secret = Str::random(64);
        try {
            $challenge = $this->otp->issue($phone, $secret, (string) $request->ip(), $request->user()?->id);
        } catch (TooManyRequestsHttpException $exception) {
            return $this->rateLimitedResponse($request, $exception);
        } catch (SmsDeliveryException $exception) {
            $request->session()->forget('phone_auth');
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 502)->header('Cache-Control', 'private, no-store, max-age=0');
            }
            $errors = (new ViewErrorBag)->put('default', new MessageBag(['phone' => [$exception->getMessage()]]));

            return response()->view('auth.phone-start', ['phone' => $phone, 'errors' => $errors] + $this->otp->sendStatus($phone, (string) $request->ip()), 502)->header('Cache-Control', 'private, no-store, max-age=0');
        }
        $request->session()->put('phone_auth', ['challenge' => $challenge->id, 'secret' => $secret]);

        return redirect()->route('phone.verify')->with('sms_sent', true);
    }

    public function rateLimitedResponse(Request $request, TooManyRequestsHttpException $exception)
    {
        $challenge = $this->pending($request);
        $phone = $challenge?->phone ?? $request->input('phone', $request->user()?->phone ?? '');
        try {
            $phone = is_string($phone) ? PhoneOtpService::normalizePhone($phone) : '';
        } catch (InvalidArgumentException) {
            $phone = '';
        }
        $status = $phone !== '' ? $this->otp->sendStatus($phone, (string) $request->ip()) : $this->initialSendStatus();
        $headers = $exception->getHeaders();
        $exceptionUntil = isset($headers['X-RateLimit-Reset']) && is_numeric($headers['X-RateLimit-Reset'])
            ? CarbonImmutable::createFromTimestampUTC((int) $headers['X-RateLimit-Reset'])
            : CarbonImmutable::now()->addSeconds(max(1, (int) ($headers['Retry-After'] ?? 60)));
        if ($exceptionUntil->isAfter($status['retryAvailableAt']) || (isset($headers['X-RateLimit-Limit']) && $exceptionUntil->equalTo($status['retryAvailableAt']))) {
            $status['resendAvailableAt'] = $status['retryAvailableAt'] = $exceptionUntil;
            $status['retryReason'] = 'ip';
            $status['limitMessage'] = 'Juda ko‘p urinish. Biroz kuting va qayta urinib ko‘ring.';
        }
        $status['retryAfter'] = max(1, (int) ceil(now()->diffInSeconds($status['retryAvailableAt'], false)));
        $message = $status['limitMessage'] ?? 'Biroz kuting. Kodni qayta yuborish vaqti hali kelmadi.';
        $responseHeaders = ['Retry-After' => (string) $status['retryAfter'], 'Cache-Control' => 'private, no-store, max-age=0'];
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message, 'retry_after' => $status['retryAfter'], 'retry_available_at' => $status['retryAvailableAt']->toIso8601String(),
                'retry_reason' => $status['retryReason'], 'sent_count' => $status['sentCount'], 'max_sends' => $status['maxSends'],
            ], 429, $responseHeaders);
        }
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['phone' => [$message]]));
        $data = $challenge ? $this->verificationData($request, $challenge, $status) : ['phone' => $phone];

        return response()->view($challenge ? 'auth.phone-verify' : 'auth.phone-start', array_replace($data, $status, ['errors' => $errors]), 429, $responseHeaders);
    }

    private function initialSendStatus(): array
    {
        return [
            'sentCount' => 0, 'maxSends' => (int) config('sms.otp.phone_hourly_limit'),
            'phoneBlockedUntil' => null, 'retryAvailableAt' => CarbonImmutable::now(), 'resendAvailableAt' => CarbonImmutable::now(),
            'retryReason' => null, 'limitMessage' => null, 'retryAfter' => 0,
        ];
    }

    private function pending(Request $request): ?PhoneLoginChallenge
    {
        return $this->otp->pending(
            $request->session()->get('phone_auth.challenge'),
            $request->session()->get('phone_auth.secret'),
            $request->user()?->id,
        );
    }
}
