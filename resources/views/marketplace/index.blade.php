@extends('layouts.app')
@section('title', 'Avtomobillar, zapchast va moto — BuxoroAvto')
@section('content')
<main id="main" class="shell pt-9">
    @if (request()->routeIs('home') && empty($filters))
        <section class="relative overflow-hidden rounded-3xl bg-[#e9eeeb] px-6 py-10 sm:px-10 sm:py-14">
            <div class="absolute -right-14 -top-20 h-80 w-80 rounded-full border-[45px] border-emerald-900/5" aria-hidden="true"></div>
            <p class="eyebrow">YO‘LINGIZ SHU YERDAN BOSHLANADI</p>
            <h1 class="mt-4 max-w-2xl text-4xl font-extrabold leading-tight tracking-tight sm:text-6xl">Siz izlagan avto olam.<br><span class="text-emerald-800">Hammasi bir joyda.</span></h1>
            <p class="mt-5 max-w-xl text-base leading-7 text-slate-600">Yangi avtomobil, kerakli ehtiyot qism yoki navbatdagi moto. O‘zingizga mos taklifni toping.</p>
            <a href="#qidiruv" class="btn btn-ink mt-7">Takliflarni ko‘rish <i data-icon="arrow-up-right" aria-hidden="true"></i></a>
        </section>
    @else
        <p class="eyebrow">MARKETPLACE</p><h1 class="page-heading mt-3">{{ ['cars' => 'Avtomobillar', 'parts' => 'Ehtiyot qismlar', 'moto' => 'Moto'][$filters['section'] ?? ''] ?? 'Barcha takliflar' }}</h1>
    @endif
    <div class="my-7 grid grid-cols-3 gap-3 sm:gap-5">
        @foreach (['cars' => ['Avtomobillar', 'Yangi · B/u · Arenda', 'car-front'], 'parts' => ['Zapchast', 'Detallar va do‘konlar', 'wrench'], 'moto' => ['Moto', 'Mototsikl · Skuter · Moped', 'bike']] as $key => $item)
            <a href="{{ route('listings', ['section' => $key]) }}" class="panel flex flex-col items-start gap-3 p-4 transition-colors hover:border-emerald-700 sm:flex-row sm:items-center sm:p-6 {{ ($filters['section'] ?? '') === $key ? 'border-emerald-700 bg-emerald-50' : '' }}"><span class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100"><i data-icon="{{ $item[2] }}" aria-hidden="true"></i></span><span><strong class="block text-sm sm:text-base">{{ $item[0] }}</strong><span class="mt-1 hidden text-xs text-slate-500 sm:block">{{ $item[1] }}</span></span></a>
        @endforeach
    </div>
    <section id="qidiruv" class="panel p-5 sm:p-6" x-data="{ expanded: false }">
        <form method="GET" action="{{ route('listings') }}" data-filter-form>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="field">Nima qidiryapsiz?<input class="input" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Model, detal yoki e’lon nomi"></label>
                <label class="field">Yo‘nalish<select class="select" name="section"><option value="">Barcha yo‘nalishlar</option value="cars" @selected(($filters['section'] ?? '') === 'cars')>Avtomobillar</option><option value="parts" @selected(($filters['section'] ?? '') === 'parts')>Zapchast</option><option value="moto" @selected(($filters['section'] ?? '') === 'moto')>Moto</option></select></label>
                <label class="field">Holati<select class="select" name="condition"><option value="">Barchasi</option value="new" @selected(($filters['condition'] ?? '') === 'new')>Yangi</option><option value="used" @selected(($filters['condition'] ?? '') === 'used')>B/u</option></select></label>
                <label class="field">Shahar / tuman<input class="input" name="city" value="{{ $filters['city'] ?? '' }}" maxlength="100" placeholder="Masalan, Buxoro"></label>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                <label class="field">Valyuta<select class="select" name="currency"><option value="UZS" @selected(($filters['currency'] ?? 'UZS') === 'UZS')>So‘m</option><option value="USD" @selected(($filters['currency'] ?? '') === 'USD')>USD</option></select></label>
                <label class="field">Narx, dan<input class="input" type="number" name="price_min" min="0" step="0.01" value="{{ $filters['price_min'] ?? '' }}" placeholder="0"></label>
                <label class="field">Narx, gacha<input class="input" type="number" name="price_max" min="0" step="0.01" value="{{ $filters['price_max'] ?? '' }}" placeholder="Cheklanmagan"></label>
                <label class="field">Taklif turi<select class="select" name="offer_type"><option value="">Barchasi</option><option value="sale" @selected(($filters['offer_type'] ?? '') === 'sale')>Sotuv</option><option value="rent" @selected(($filters['offer_type'] ?? '') === 'rent')>Arenda</option></select></label>
                <label class="field">Tartiblash<select class="select" name="sort"><option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Eng yangi</option><option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Arzonidan</option><option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Qimmatidan</option></select></label>
            </div>
            <div class="mt-5" x-cloak x-show="expanded"><div data-price-range class="mx-3 my-7" data-max="{{ ($filters['currency'] ?? 'UZS') === 'USD' ? 200000 : 2000000000 }}"></div><p class="muted">Aniq narxni yuqoridagi maydonlarga ham kiritishingiz mumkin.</p></div>
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3"><button type="button" class="btn btn-ghost" @click="expanded = !expanded" :aria-expanded="expanded">Narx shkalasi <i data-icon="sliders-horizontal" aria-hidden="true"></i></button><div class="flex gap-2"><a href="{{ route('listings') }}" class="btn btn-ghost">Tozalash</a><button class="btn btn-brand" type="submit"><i data-icon="search" aria-hidden="true"></i>Qidirish</button></div></div>
        </form>
    </section>
    <section class="mt-10"><div class="mb-5 flex items-center justify-between"><h2 class="text-2xl font-bold tracking-tight">{{ empty($filters) ? 'Yangi e’lonlar' : 'Qidiruv natijalari' }}</h2><span class="muted">{{ $listings->count() }} ta taklif{{ $listings->hasMorePages() ? ' · Davomi bor' : '' }}</span></div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">@foreach ($listings as $listing)<x-listing-card :listing="$listing" />@endforeach</div>
        @if ($listings->isEmpty())<div class="panel px-6 py-16 text-center"><i data-icon="search" class="mb-4 !h-10 !w-10 text-slate-400" aria-hidden="true"></i><h3 class="text-xl font-semibold">Hozircha e’lonlar topilmadi</h3><p class="muted mt-2">Qidiruv shartlarini o‘zgartiring yoki birinchi e’lonni joylashtiring.</p><a href="{{ route('cabinet.listing.create') }}" class="btn btn-brand mt-6">E’lon berish</a></div>@endif
        <div class="mt-7">{{ $listings->links() }}</div>
    </section>
</main>
@endsection
