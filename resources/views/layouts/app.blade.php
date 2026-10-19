<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', config('app.name'))</title>
        <meta name="description" content="@yield('description', 'BuxoroAvto — avtomobillar, ehtiyot qismlar va moto. Sotuvchilar va xaridorlar uchun yagona marketplace.')">
        @hasSection('robots')<meta name="robots" content="@yield('robots')">@endif
        <meta name="theme-color" content="#192327">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="min-h-screen font-sans antialiased" x-data="{ mobileMenu: false }">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-white focus:p-4">Asosiy tarkibga o‘tish</a>
        <div class="bg-ink text-white"><div class="shell flex h-9 items-center justify-between text-xs"><span>Avto olam — bir joyda</span><span>Buxoro · O‘zbekiston</span></div></div>
        <header class="border-b border-slate-200 bg-white">
            <div class="shell flex h-20 items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-base font-extrabold tracking-tight sm:text-xl" aria-label="BuxoroAvto bosh sahifa"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand text-xl sm:h-10 sm:w-10">B<span class="text-emerald-800">.</span></span>Buxoro<span class="-ml-2 font-normal">Avto</span></a>
                <nav class="hidden items-center gap-6 text-sm font-semibold md:flex" aria-label="Asosiy menyu">
                    <a href="{{ route('listings', ['section' => 'cars']) }}">Avtomobillar</a><a href="{{ route('listings', ['section' => 'parts']) }}">Zapchast</a><a href="{{ route('listings', ['section' => 'moto']) }}">Moto</a>
                </nav>
                <div class="flex items-center gap-2">
                    <a href="{{ route('cabinet') }}" class="btn btn-ghost hidden sm:inline-flex"><i data-icon="user-round" aria-hidden="true"></i>Kabinet</a>
                    <a href="{{ route('cabinet.listing.create') }}" class="btn btn-brand btn-sm sm:btn-md"><i data-icon="plus" aria-hidden="true"></i>E’lon berish</a>
                    <button type="button" class="btn btn-ghost btn-sm md:hidden" @click="mobileMenu = !mobileMenu" :aria-expanded="mobileMenu" aria-controls="mobile-menu" aria-label="Menyuni ochish"><i data-icon="menu" aria-hidden="true"></i></button>
                </div>
            </div>
            <nav id="mobile-menu" x-cloak x-show="mobileMenu" class="shell flex flex-col gap-4 pb-5 text-sm md:hidden" aria-label="Mobil menyu"><a href="{{ route('listings', ['section' => 'cars']) }}">Avtomobillar</a><a href="{{ route('listings', ['section' => 'parts']) }}">Zapchast</a><a href="{{ route('listings', ['section' => 'moto']) }}">Moto</a><a href="{{ route('cabinet') }}">Kabinet</a></nav>
        </header>
        @if ($errors->any())<div role="alert" class="shell pt-5"><div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
        @yield('content')
        <footer class="mt-16 border-t border-slate-200 bg-white py-8"><div class="shell flex flex-wrap justify-between gap-4 text-sm"><span class="font-bold">BuxoroAvto <span class="font-normal text-slate-500">© {{ now()->year }}</span></span><span class="text-slate-500">Avtomobil · Zapchast · Moto</span></div></footer>
        <div x-data="toast" @notice.window="show($event.detail)" x-cloak x-show="message" class="fixed bottom-6 left-4 right-4 z-50 mx-auto max-w-lg rounded-xl bg-ink p-4 text-sm text-white shadow-xl" role="status" aria-live="polite" x-text="message"></div>
        @stack('scripts')
    </body>
</html>
