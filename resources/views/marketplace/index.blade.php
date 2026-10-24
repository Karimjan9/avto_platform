@extends('layouts.app')
@section('title', 'Avtomobillar, ehtiyot qismlar va moto — BuxoroAvto')
@section('content')
@php
    $isHome = request()->routeIs('home') && empty($filters);
    $sectionTitle = ['cars' => 'Avtomobillar', 'parts' => 'Ehtiyot qismlar', 'moto' => 'Moto'][$filters['section'] ?? ''] ?? 'Barcha takliflar';
@endphp
<main id="main" class="shell market-main">
    @if ($isHome)
        @include('marketplace.home-hero')
    @else
        <div class="market-page-heading"><div><a href="{{ route('home') }}" class="market-breadcrumb">Bosh sahifa <span aria-hidden="true">/</span></a><h1>{{ $sectionTitle }}</h1></div><span class="market-page-note">O‘zingizga mos taklifni toping</span></div>
        @include('marketplace.filters')
    @endif
    <section class="market-recommendations" id="elonlar" aria-labelledby="listings-heading">
        <div class="market-section-heading"><h2 id="listings-heading">{{ $isHome ? 'Siz uchun tavsiyalar' : 'Qidiruv natijalari' }}</h2>
            @if ($isHome)<a href="{{ route('listings') }}">Barchasini ko‘rish <i data-icon="chevron-right" aria-hidden="true"></i></a>
            @else<span class="market-result-count">{{ $listings->count() }} ta taklif{{ $listings->hasMorePages() ? ' · Davomi bor' : '' }}</span>@endif
        </div>
        @if ($listings->isNotEmpty())
            <div class="market-listing-grid">@foreach ($listings as $listing)<x-listing-card :listing="$listing" />@endforeach</div>
        @else
            <div class="market-empty"><span class="market-empty-icon"><i data-icon="search" aria-hidden="true"></i></span><div><h3>{{ $isHome ? 'Yangi takliflar shu yerda paydo bo‘ladi' : 'Hozircha e’lonlar topilmadi' }}</h3><p>{{ $isHome ? 'Avtomobil, ehtiyot qism yoki moto — birinchi e’loningizni joylashtiring.' : 'Qidiruv shartlarini o‘zgartiring yoki barcha takliflarni ko‘ring.' }}</p></div><a href="{{ $isHome ? route('cabinet.listing.create') : route('listings') }}" class="btn btn-brand">{{ $isHome ? 'E’lon berish' : 'Barcha takliflar' }} <i data-icon="arrow-right" aria-hidden="true"></i></a></div>
        @endif
        @if (!$isHome)<div class="mt-7">{{ $listings->links() }}</div>@endif
    </section>
    @if ($isHome)
        @if (($presentationMode ?? false) && $partOffers->isNotEmpty())
            @include('marketplace.part-offers')
        @else
        <section class="market-offers-banner" aria-label="Ehtiyot qismlar takliflari"><div><span class="market-offers-eyebrow">KERAKLI DETAL. QULAY TANLOV.</span><h2>Bir detal — turli do‘konlar taklifi</h2><p>Mos ehtiyot qismni toping. Narx, mavjudlik va sotuvchini tanlang.</p></div><div class="market-offers-steps"><span><i data-icon="car-front" aria-hidden="true"></i> Avtomobilingizni tanlang</span><i data-icon="arrow-right" aria-hidden="true"></i><span><i data-icon="wrench" aria-hidden="true"></i> Mos detalni toping</span></div><a href="{{ route('listings', ['section' => 'parts']) }}" class="btn btn-brand">Ehtiyot qismlarni ko‘rish <i data-icon="arrow-right" aria-hidden="true"></i></a></section>
        @endif
    @endif
    @if ($presentationMode ?? false)<p class="market-demo-note">Namoyish rejimi: e’lonlar, narxlar, reytinglar va tasdiqlash belgilari dizaynni ko‘rsatish uchun test ma’lumotlaridir.</p>@endif
</main>
@endsection
