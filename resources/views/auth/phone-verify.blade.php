@extends('layouts.app')
@section('title', 'SMS kodni tasdiqlash — '.config('brand.name'))
@section('robots', 'noindex,nofollow')
@section('local-form-errors', '1')
@section('content')
@php
    $smsAvailable = (bool) config('sms.enabled');
    $cooldownSeconds = max(0, (int) ($retryAfter ?? ceil(now()->diffInSeconds($resendAvailableAt, false))));
    $codeSeconds = max(0, (int) ceil(now()->diffInSeconds($expiresAt, false)));
    $maxSends = max(1, (int) ($maxSends ?? config('sms.otp.phone_hourly_limit', 3)));
    $sentCount = max(0, min($maxSends, (int) ($sentCount ?? 1)));
    $limitMessage = $limitMessage ?? null;
    $showLimitNotice = $cooldownSeconds > 60 || ($errors->has('phone') && $limitMessage) || ($sentCount >= $maxSends && $cooldownSeconds > 0);
    $showPhoneError = $errors->has('phone') && (! $showLimitNotice || $errors->first('phone') !== $limitMessage);
    $formatCountdown = static fn (int $seconds): string => ($seconds >= 3600 ? intdiv($seconds, 3600).':'.str_pad((string) intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT) : (string) intdiv($seconds, 60)).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
@endphp
<main id="main" class="shell phone-auth-page">
    <div class="phone-auth-layout">
        @include('auth.benefits')
        <section class="phone-auth-card" aria-labelledby="phone-code-title" data-sent-count="{{ $sentCount }}" data-max-sends="{{ $maxSends }}" x-data="{ smsEnabled: @js($smsAvailable), cooldown: {{ $cooldownSeconds }}, initialCooldown: {{ $cooldownSeconds }}, remaining: {{ $codeSeconds }}, initialRemaining: {{ $codeSeconds }}, started: 0, sending: false, verifying: false, format(seconds) { const hours = Math.floor(seconds / 3600); const minutes = Math.floor((seconds % 3600) / 60); return (hours ? hours + ':' + String(minutes).padStart(2, '0') : String(minutes)) + ':' + String(seconds % 60).padStart(2, '0') }, tick() { const elapsed = Math.floor((performance.now() - this.started) / 1000); this.cooldown = Math.max(0, this.initialCooldown - elapsed); this.remaining = Math.max(0, this.initialRemaining - elapsed) } }" x-init="started = performance.now(); setInterval(() => tick(), 250)">
            <ol class="phone-auth-steps" aria-label="Kirish bosqichlari"><li class="is-done"><b aria-label="Tayyor">✓</b>Telefon</li><li class="is-current" aria-current="step"><b>2</b>SMS kod</li><li><b>3</b>Profil</li></ol>
            <span class="phone-auth-symbol"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 4h16v12H9l-5 4V4Z"/><path d="M8 8h8M8 12h5"/></svg></span>
            <h1 id="phone-code-title">SMS kodni kiriting</h1>
            <p class="phone-auth-description"><strong>{{ $maskedPhone }}</strong> raqamiga yuborilgan 6 xonali kodni kiriting.</p>
            @if ($smsAvailable)
                <div class="phone-auth-sent" role="status" data-sms-sent><span class="phone-auth-notice-icon" aria-hidden="true">✓</span><div><strong>{{ $sentCount > 1 ? 'Yangi kod yuborildi!' : 'Kod yuborildi!' }}</strong><p>SMSdagi 6 xonali kodni quyida kiriting.</p></div></div>
            @else
                <div class="phone-auth-availability" role="status" data-sms-unavailable><span class="phone-auth-notice-icon" aria-hidden="true">ⓘ</span><div><strong>Yangi SMS yuborish vaqtincha mavjud emas</strong><p>Avval yuborilgan kod amal qilish muddati tugamaguncha ishlaydi.</p></div></div>
            @endif
            @if ($showLimitNotice)
                <div class="phone-auth-limit" role="status" x-show="cooldown > 0" data-phone-limit><span class="phone-auth-notice-icon" aria-hidden="true">◷</span><div><strong>Yangi SMS uchun kuting</strong><p>{{ $limitMessage ?: 'Bir soatda '.$maxSends.' ta kod so‘rash mumkin. Ko‘rsatilgan vaqtdan keyin qayta urinib ko‘ring.' }}</p><p class="phone-auth-wait">Qayta urinishgacha: <span role="timer" aria-live="off" x-text="format(cooldown)" data-retry-countdown>{{ $formatCountdown($cooldownSeconds) }}</span></p></div></div>
            @endif
            <form method="POST" action="{{ route('phone.verify.store') }}" class="phone-auth-form" @submit="verifying = true">@csrf
                <label for="auth-code" class="phone-auth-label">6 xonali kirish kodi</label>
                <input id="auth-code" class="phone-auth-code {{ $errors->has('code') ? 'has-error' : '' }}" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required autofocus placeholder="000000" data-otp-input aria-describedby="auth-code-help{{ $errors->has('code') ? ' auth-code-error' : '' }}" @if ($errors->has('code')) aria-invalid="true" @endif>
                @error('code')<p id="auth-code-error" class="phone-auth-error" role="alert">{{ $message }}</p>@enderror
                @if ($showPhoneError)<p class="phone-auth-error" role="alert">{{ $errors->first('phone') }}</p>@endif
                <p id="auth-code-help" class="phone-auth-help"><span x-show="remaining > 0" @if ($codeSeconds === 0) x-cloak @endif>Kodning amal qilish muddati: <span role="timer" aria-live="off" x-text="format(remaining)" data-code-countdown>{{ $formatCountdown($codeSeconds) }}</span></span><span x-show="remaining === 0" @if ($codeSeconds > 0) x-cloak @endif>Kodning muddati tugadi. Yangi kod so‘rang.</span></p>
                <button type="submit" class="phone-auth-submit" :disabled="remaining === 0 || verifying" @disabled($codeSeconds === 0)><span x-show="!verifying">Tasdiqlash va davom etish</span><span x-show="verifying" x-cloak>Tekshirilmoqda…</span><i data-icon="arrow-right" aria-hidden="true"></i></button>
            </form>
            <div class="phone-auth-resend"><span>SMS kelmadimi?</span><form method="POST" action="{{ route('phone.resend') }}" @submit="sending = true" data-phone-resend>@csrf<button type="submit" :disabled="!smsEnabled || cooldown > 0 || sending" @disabled(! $smsAvailable || $cooldownSeconds > 0)><span x-show="sending" x-cloak>Yuborilmoqda…</span><span x-show="!sending &amp;&amp; cooldown > 0" @if ($cooldownSeconds === 0) x-cloak @endif>Qayta yuborish (<span role="timer" aria-live="off" x-text="format(cooldown)" data-resend-countdown>{{ $formatCountdown($cooldownSeconds) }}</span>)</span><span x-show="!sending &amp;&amp; cooldown === 0" @if ($cooldownSeconds > 0) x-cloak @endif>Kodni qayta yuborish</span></button></form></div>
            <p class="phone-auth-send-policy">Bir soatda {{ $maxSends }} ta kod so‘rash mumkin.<span>Yuborildi: <strong data-sms-count>{{ $sentCount }}/{{ $maxSends }}</strong></span></p>
            <a href="{{ route('phone.start') }}" class="phone-auth-back">← Telefon raqamini o‘zgartirish</a>
            <p class="phone-auth-note">Kodni hech kimga bermang. {{ config('brand.name') }} xodimlari sizdan kirish kodingizni so‘ramaydi.</p>
        </section>
    </div>
</main>
@endsection
