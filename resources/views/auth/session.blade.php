@extends('layouts.app')
@section('title', ($register ? 'Ro‘yxatdan o‘tish' : 'Kirish').' — BuxoroAvto')
@section('robots', 'noindex,nofollow')
@section('content')
<main id="main" class="shell py-12"><section class="panel mx-auto max-w-md p-7 sm:p-9"><p class="eyebrow">SHAXSIY KABINET</p><h1 class="mt-3 text-3xl font-bold">{{ $register ? 'Xush kelibsiz' : 'Yana ko‘rishganimizdan xursandmiz' }}</h1><p class="muted mt-3">E’lonlaringiz va biznesingizni bir joydan boshqaring.</p>
    <form method="POST" action="{{ $register ? route('register') : route('login') }}" class="mt-7 space-y-5">@csrf
        @if ($register)<label class="field">Ismingiz<input class="input" name="name" autocomplete="name" required minlength="2" maxlength="120" value="{{ old('name') }}"></label>@endif
        <label class="field">Email<input class="input" name="email" type="email" autocomplete="username" required maxlength="255" value="{{ old('email') }}"></label>
        <label class="field">Parol<input class="input" name="password" type="password" autocomplete="{{ $register ? 'new-password' : 'current-password' }}" required maxlength="72" @if ($register) minlength="8" @endif></label>
        @if ($register)<label class="field">Parolni takrorlang<input class="input" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8" maxlength="72"></label><p class="muted">Kamida 8 ta belgi, harf va raqam ishlating.</p>@endif
        <button class="btn btn-brand w-full" type="submit">{{ $register ? 'Akkaunt yaratish' : 'Kirish' }}</button>
    </form>
    <p class="muted mt-6 text-center">{{ $register ? 'Akkauntingiz bormi?' : 'Yangi foydalanuvchimisiz?' }} <a class="font-semibold text-violet-800 underline" href="{{ route('login') }}">Telefon orqali kirish</a></p>
</section></main>
@endsection
