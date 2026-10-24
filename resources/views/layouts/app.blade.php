<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @php
            $pageTitle = html_entity_decode(trim($__env->yieldContent('title', config('app.name'))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $pageDescription = html_entity_decode(trim($__env->yieldContent('description', 'BuxoroAvto — avtomobillar, ehtiyot qismlar va moto. Sotuvchilar va xaridorlar uchun yagona marketplace.')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $brandVersion = '20261024b';
            $shareImage = asset('brand/buxoroavto-share.png').'?v='.$brandVersion;
        @endphp
        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $pageDescription }}">
        @hasSection('robots')<meta name="robots" content="@yield('robots')">@endif
        <meta name="theme-color" content="#5d3bfa">
        <meta name="application-name" content="BuxoroAvto">
        <meta name="apple-mobile-web-app-title" content="BuxoroAvto">
        <link rel="icon" type="image/x-icon" sizes="16x16 32x32 48x48" href="{{ asset('favicon.ico') }}?v={{ $brandVersion }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v={{ $brandVersion }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v={{ $brandVersion }}">
        <link rel="icon" type="image/svg+xml" sizes="any" href="{{ asset('favicon.svg') }}?v={{ $brandVersion }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v={{ $brandVersion }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}?v={{ $brandVersion }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="BuxoroAvto">
        <meta property="og:locale" content="uz_UZ">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ $shareImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="BuxoroAvto — avto, ehtiyot qism va moto bir joyda">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $pageTitle }}">
        <meta name="twitter:description" content="{{ $pageDescription }}">
        <meta name="twitter:image" content="{{ $shareImage }}">
        <meta name="twitter:image:alt" content="BuxoroAvto — avto, ehtiyot qism va moto bir joyda">
        <link rel="preload" href="{{ asset('fonts/roboto-latin-variable.woff2') }}" as="font" type="font/woff2" crossorigin>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="min-h-screen font-sans antialiased" x-data="{ mobileMenu: false }">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-white focus:p-4">Asosiy tarkibga o‘tish</a>
        <header class="site-header">
            <div class="site-topbar">
                <div class="shell site-topbar-inner">
                    <div class="site-topbar-start">
                        <details class="site-location-picker">
                            <summary class="site-location"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7Zm0 10a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/></svg><span>Buxoro</span><svg class="site-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg></summary>
                            <div class="site-utility-popover"><a href="{{ route('listings', ['city' => 'Buxoro']) }}">Buxoro shahri</a><a href="{{ route('listings') }}">Barcha hududlar</a></div>
                        </details>
                        <span class="site-city-name">Buxoro shahri</span>
                        <span class="site-demo-weather" title="Namuna ob-havo: +28°C. Jonli ob-havo ma’lumoti emas." aria-label="Namuna ob-havo: +28 daraja"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="4" fill="currentColor" stroke="none"/><path d="M12 1v3m0 16v3M1 12h3m16 0h3M4.2 4.2l2.1 2.1m11.4 11.4 2.1 2.1M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></svg><span>+28°C</span></span>
                    </div>
                    <div class="site-topbar-end">
                        <button type="button" class="site-utility-link" @click="$refs.helpDialog.showModal()" aria-haspopup="dialog" aria-controls="site-help-dialog">Yordam</button>
                        <a href="{{ route('cabinet') }}" class="site-utility-link">Sotuvchilar uchun</a>
                        <details class="site-language" lang="uz"><summary>O‘zbekcha <svg class="site-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg></summary><div class="site-utility-popover"><span>O‘zbekcha <small>Tanlangan</small></span></div></details>
                        @auth
                            <a href="{{ route('cabinet') }}" class="site-login"><i data-icon="user-round" aria-hidden="true"></i>{{ Str::limit(auth()->user()->name, 24) }}</a>
                        @else
                            <a href="{{ route('login') }}" class="site-login"><i data-icon="user-round" aria-hidden="true"></i>Kirish</a>
                        @endauth
                    </div>
                </div>
            </div>
            <div class="shell site-header-main">
                <a href="{{ route('home') }}" class="site-brand" aria-label="BuxoroAvto bosh sahifa">
                    <img class="site-brand-mark" src="{{ asset('brand/buxoroavto-mark.svg') }}?v={{ $brandVersion }}" width="41" height="41" alt="" aria-hidden="true" decoding="async">
                    <span class="site-brand-name">Buxoro<span>Avto</span></span>
                </a>
                <a href="{{ route('listings') }}" class="site-catalog-button">
                    <i data-icon="menu" aria-hidden="true"></i>
                    Katalog
                </a>
                <form method="GET" action="{{ route('listings') }}" class="site-search" role="search" aria-label="Sayt bo‘yicha qidiruv">
                    <label for="site-search-query" class="sr-only">Saytdan qidirish</label>
                    <input id="site-search-query" type="search" name="q" value="{{ is_string(request()->query('q')) ? request()->query('q') : '' }}" placeholder="Avtomobil, detal yoki model qidiring" maxlength="100">
                    <button type="submit" aria-label="Saytdan qidirish"><i data-icon="search" aria-hidden="true"></i></button>
                </form>
                <div class="site-header-actions">
                    <button type="button" class="site-account-link" data-saved-open aria-controls="saved-listings" aria-expanded="false" aria-haspopup="dialog"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg><span>Saqlangan</span><span class="site-saved-count" data-saved-count hidden>0</span></button>
                    <a href="{{ route('cabinet') }}" class="site-account-link"><i data-icon="user-round" aria-hidden="true"></i><span>Kabinet</span></a>
                </div>
                <a href="{{ route('cabinet.listing.create') }}" class="site-post-button"><i data-icon="plus" aria-hidden="true"></i><span>E’lon berish</span></a>
                <button type="button" class="site-menu-toggle" @click="mobileMenu = !mobileMenu" :aria-expanded="mobileMenu" aria-controls="mobile-menu" aria-label="Menyuni ochish"><i data-icon="menu" aria-hidden="true"></i></button>
            </div>
            @php
                $siteRental = request()->query('offer_type') === 'rent';
                $siteSection = request()->query('section');
            @endphp
            <nav class="shell site-category-nav" aria-label="Asosiy menyu">
                <a class="site-category-link {{ $siteSection === 'cars' && !$siteRental ? 'is-active' : '' }}" href="{{ route('listings', ['section' => 'cars']) }}" @if ($siteSection === 'cars' && !$siteRental) aria-current="page" @endif><i data-icon="car-front" aria-hidden="true"></i><span>Avtomobillar</span></a>
                <a class="site-category-link {{ $siteSection === 'parts' ? 'is-active' : '' }}" href="{{ route('listings', ['section' => 'parts']) }}" @if ($siteSection === 'parts') aria-current="page" @endif><i data-icon="wrench" aria-hidden="true"></i><span>Ehtiyot qismlar</span></a>
                <a class="site-category-link {{ $siteSection === 'moto' ? 'is-active' : '' }}" href="{{ route('listings', ['section' => 'moto']) }}" @if ($siteSection === 'moto') aria-current="page" @endif><i data-icon="bike" aria-hidden="true"></i><span>Moto</span></a>
                <a class="site-category-link {{ $siteRental ? 'is-active' : '' }}" href="{{ route('listings', ['section' => 'cars', 'offer_type' => 'rent']) }}" @if ($siteRental) aria-current="page" @endif><i data-icon="car-front" aria-hidden="true"></i><span>Arenda</span></a>
            </nav>
            <nav id="mobile-menu" x-cloak x-show="mobileMenu" class="site-mobile-menu" aria-label="Mobil menyu">
                <div class="shell site-mobile-menu-inner"><a href="{{ route('listings', ['section' => 'cars']) }}">Avtomobillar</a><a href="{{ route('listings', ['section' => 'parts']) }}">Ehtiyot qismlar</a><a href="{{ route('listings', ['section' => 'moto']) }}">Moto</a><a href="{{ route('listings', ['section' => 'cars', 'offer_type' => 'rent']) }}">Avto arenda</a><button type="button" data-saved-open aria-controls="saved-listings" aria-expanded="false" aria-haspopup="dialog">Saqlangan e’lonlar</button><a href="{{ route('cabinet') }}">Kabinet</a><button type="button" @click="$refs.helpDialog.showModal()" aria-haspopup="dialog" aria-controls="site-help-dialog">Yordam</button>@guest<a href="{{ route('login') }}">Kirish / Ro‘yxatdan o‘tish</a>@endguest</div>
            </nav>
        </header>
        @if ($errors->any() && !$__env->hasSection('local-form-errors'))<div role="alert" class="shell pt-5"><div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
        @yield('content')
        <footer class="site-footer"><div class="shell site-footer-inner"><a href="{{ route('home') }}" class="site-footer-brand"><img src="{{ asset('brand/buxoroavto-mark.svg') }}?v={{ $brandVersion }}" width="28" height="28" alt="" decoding="async">Buxoro<span>Avto</span><small>© {{ now()->year }}</small></a><p>Avtomobillar, ehtiyot qismlar va moto — hammasi bir joyda.</p><a href="{{ route('cabinet.listing.create') }}">E’lon joylashtirish <span aria-hidden="true">↗</span></a></div></footer>
        <dialog id="site-help-dialog" class="site-help-dialog" x-ref="helpDialog" aria-labelledby="site-help-title"><div class="site-help-heading"><h2 id="site-help-title">BuxoroAvto yordam</h2><form method="dialog"><button type="submit" aria-label="Yordam oynasini yopish">×</button></form></div><div class="site-help-content"><h3>Kerakli taklifni qanday topaman?</h3><p>Qidiruvga model yoki detal nomini yozing. Katalogda yo‘nalish, holat, shahar va narx bo‘yicha filtrlang.</p><h3>E’lonni qanday joylashtiraman?</h3><p>Akkauntingizga kiring, “E’lon berish” tugmasini bosing va ma’lumotlarni to‘ldiring. Qoralamani saqlash yoki moderatsiyaga yuborish mumkin.</p><h3>Saqlangan e’lonlar qayerda?</h3><p>E’londagi yurak belgisini bosing. Saqlanganlar shu brauzerda turadi.</p><div class="site-help-actions"><a href="{{ route('listings') }}" class="btn btn-brand">Katalogni ochish</a><a href="{{ route('cabinet') }}" class="btn btn-ghost">Kabinetga o‘tish</a></div></div></dialog>
        <dialog id="saved-listings" class="site-saved-dialog" data-saved-panel hidden aria-labelledby="saved-listings-title" aria-describedby="saved-listings-description">
            <div class="site-saved-heading"><div><h2 id="saved-listings-title">Saqlangan e’lonlar</h2><p id="saved-listings-description">Shu brauzerda saqlangan e’lonlar.</p></div><button type="button" data-saved-close aria-label="Saqlanganlar oynasini yopish"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
            <div class="site-saved-content" data-saved-content><div class="site-saved-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg><h3>Hozircha saqlangan e’lon yo‘q</h3><p>Yoqtirgan e’loningizdagi yurak belgisini bosing. Uni shu yerdan topasiz.</p><a href="{{ route('listings') }}" class="btn btn-brand">E’lonlarni ko‘rish</a></div></div>
        </dialog>
        <div x-data="toast" @notice.window="show($event.detail)" x-cloak x-show="message" class="fixed bottom-6 left-4 right-4 z-50 mx-auto max-w-lg rounded-xl bg-ink p-4 text-sm text-white shadow-xl" role="status" aria-live="polite" x-text="message"></div>
        @stack('scripts')
    </body>
</html>
