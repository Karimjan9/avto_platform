@extends('layouts.app')
@section('title', (auth()->check() ? 'Telefonni tasdiqlash' : 'Telefon orqali kirish').' — '.config('brand.name'))
@section('robots', 'noindex,nofollow')
@section('local-form-errors', '1')
@section('content')
@php
    $enteredPhone = old('phone', $phone ?? auth()->user()?->phone ?? '');
    $enteredPhone = is_string($enteredPhone) ? preg_replace('/^\+?998/', '', $enteredPhone) : '';
    $smsAvailable = (bool) config('sms.enabled');
    $retrySeconds = max(0, (int) ($retryAfter ?? (isset($retryAvailableAt) ? ceil(now()->diffInSeconds($retryAvailableAt, false)) : 0)));
    $limitMessage = $limitMessage ?? null;
    $retryReason = $retryReason ?? null;
    $phoneInvalid = $smsAvailable && $errors->has('phone');
    $showPhoneError = $phoneInvalid && $errors->first('phone') !== $limitMessage;
    $initialRetryLabel = $retrySeconds >= 3600
        ? intdiv($retrySeconds, 3600).':'.str_pad((string) intdiv($retrySeconds % 3600, 60), 2, '0', STR_PAD_LEFT).':'.str_pad((string) ($retrySeconds % 60), 2, '0', STR_PAD_LEFT)
        : intdiv($retrySeconds, 60).':'.str_pad((string) ($retrySeconds % 60), 2, '0', STR_PAD_LEFT);
@endphp
<main id="main" class="shell phone-auth-page">
    <div class="phone-auth-layout">
        @include('auth.benefits')
        <section class="phone-auth-card" aria-labelledby="phone-auth-title" x-data="{ smsEnabled: @js($smsAvailable), retry: {{ $retrySeconds }}, initialRetry: {{ $retrySeconds }}, started: 0, format(seconds) { const hours = Math.floor(seconds / 3600); const minutes = Math.floor((seconds % 3600) / 60); return (hours ? hours + ':' + String(minutes).padStart(2, '0') : String(minutes)) + ':' + String(seconds % 60).padStart(2, '0') }, tick() { this.retry = Math.max(0, this.initialRetry - Math.floor((performance.now() - this.started) / 1000)) } }" x-init="started = performance.now(); setInterval(() => tick(), 250)">
            <ol class="phone-auth-steps" aria-label="Kirish bosqichlari"><li class="is-current" aria-current="step"><b>1</b>Telefon</li><li><b>2</b>SMS kod</li><li><b>3</b>Profil</li></ol>
            <span class="phone-auth-symbol"><i data-icon="phone" aria-hidden="true"></i></span>
            <h1 id="phone-auth-title">{{ auth()->check() ? 'Telefoningizni tasdiqlang' : 'Xush kelibsiz!' }}</h1>
            <p class="phone-auth-description">{{ auth()->check() ? 'E’lon joylashtirishdan oldin raqamingizga yuborilgan kodni tasdiqlang.' : 'Kirish yoki yangi akkaunt ochish uchun telefon raqamingizni kiriting.' }}</p>
            @unless ($smsAvailable)
                <div class="phone-auth-availability" role="status" data-sms-unavailable><span class="phone-auth-notice-icon" aria-hidden="true">ⓘ</span><div><strong>SMS xizmati vaqtincha mavjud emas</strong><p>{{ auth()->check() ? 'Telefonni SMS orqali tasdiqlash vaqtincha mavjud emas. Kabinetingizga qaytishingiz mumkin.' : 'SMS orqali kirish vaqtincha mavjud emas. Email va parol orqali kiring.' }}</p></div></div>
            @endunless
            @if ($smsAvailable && ($retrySeconds > 0 || $limitMessage))
                <div class="phone-auth-limit" id="auth-phone-retry" role="alert" x-show="retry > 0" data-phone-limit>
                    <span class="phone-auth-notice-icon" aria-hidden="true">◷</span><div><strong>{{ $retryReason === 'ip' ? 'Bir oz tanaffus qiling' : 'Yangi kod so‘rashdan oldin kuting' }}</strong><p>{{ $limitMessage ?: 'SMS so‘rovlari orasida biroz kutish kerak. So‘ng qayta urinib ko‘ring.' }}</p><p class="phone-auth-wait">Qayta urinishgacha: <span role="timer" aria-live="off" x-text="format(retry)">{{ $initialRetryLabel }}</span></p></div>
                </div>
            @endif
            <form method="POST" action="{{ route('phone.send') }}" class="phone-auth-form" data-phone-auth-form>@csrf
                <label for="auth-phone" class="phone-auth-label">Telefon raqamingiz</label>
                <div class="phone-auth-number {{ $phoneInvalid ? 'has-error' : '' }}"><span aria-hidden="true">+998</span><input id="auth-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel-national" placeholder="90 123 45 67" value="{{ $enteredPhone }}" required maxlength="20" aria-describedby="auth-phone-help{{ $showPhoneError ? ' auth-phone-error' : '' }}{{ $smsAvailable && $retrySeconds > 0 ? ' auth-phone-retry' : '' }}" @if ($phoneInvalid) aria-invalid="true" @endif autofocus></div>
                @if ($showPhoneError)<p id="auth-phone-error" class="phone-auth-error" role="alert">{{ $errors->first('phone') }}</p>@endif
                <p id="auth-phone-help" class="phone-auth-help">Shu raqamga 6 xonali kirish kodi yuboramiz.</p>
                <button type="submit" class="phone-auth-submit" data-phone-send :disabled="!smsEnabled || retry > 0" @disabled(! $smsAvailable || $retrySeconds > 0)><span x-show="retry === 0" @if ($retrySeconds > 0) x-cloak @endif>Kodni olish</span><span x-show="retry > 0" @if ($retrySeconds === 0) x-cloak @endif>Qayta urinish (<span x-text="format(retry)">{{ $initialRetryLabel }}</span>)</span><i data-icon="arrow-right" aria-hidden="true"></i></button>
            </form>
            <p class="phone-auth-note">Raqamingiz e’lonlarda xaridorlar bilan bog‘lanish uchun ishlatiladi.</p>
            @guest<div class="phone-auth-divider"><span>yoki</span></div><a href="{{ route('login.email') }}" class="phone-auth-email">Email va parol bilan kirish <i data-icon="chevron-right" aria-hidden="true"></i></a>@else<a href="{{ route('cabinet') }}" class="phone-auth-back">← Kabinetga qaytish</a>@endguest
        </section>
    </div>
</main>
@endsection
