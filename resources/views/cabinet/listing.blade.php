@extends('layouts.app')
@section('title', ($listing ? 'E’lonni tahrirlash' : 'Yangi e’lon').' — BuxoroAvto')
@section('robots', 'noindex,nofollow')
@section('content')
@php
    $activeBusinesses = $businesses->where('status', 'active')->values();
    $requestedSection = is_string(request('section')) && in_array(request('section'), ['cars', 'parts', 'moto'], true) ? request('section') : 'cars';
    $initial = $listing ? (new \App\Http\Resources\ListingResource($listing))->resolve(request()) : ['section' => $requestedSection, 'condition' => $requestedSection === 'parts' ? 'new' : 'used', 'offer_type' => 'sale', 'currency' => 'UZS', 'city' => auth()->user()->city ?: 'Buxoro', 'details' => [], 'deal_terms' => ['cash'], 'submit' => false];
    $initial['contact_phone'] = $activeBusinesses->firstWhere('id', $initial['business_id'] ?? null)?->verified_contact_phone ?? auth()->user()->phone;
@endphp
<main id="main" class="seller-editor-page"><div class="shell">@include('cabinet.nav')
    <div class="seller-editor-heading"><div><p class="seller-kicker">YANGI XARIDORLAR SARI</p><h1>{{ $listing ? 'E’lonni tahrirlash' : 'E’lon joylashtirish' }}</h1><p>Aniq ma’lumot va sifatli surat xaridorga tanlashda yordam beradi.</p></div><a href="{{ route('cabinet') }}">Mening e’lonlarim <span aria-hidden="true">↗</span></a></div>
    @if ($businesses->contains('status', 'pending'))<p class="seller-editor-store-note">Do‘koningiz tekshiruvda. Tasdiqlanguncha shaxsiy e’lon joylashtirishingiz mumkin.</p>@endif
    <nav class="seller-editor-progress" aria-label="E’lon shakli bo‘limlari"><a href="#listing-category"><b>1</b>Toifa</a><a href="#listing-about"><b>2</b>Ma’lumotlar</a><a href="#listing-photos"><b>3</b>Suratlar</a><a href="#listing-contact"><b>4</b>Aloqa</a></nav>
    <form class="seller-editor-form" data-listing-editor data-guided="true" data-initial="{{ json_encode($initial) }}" data-businesses="{{ json_encode($activeBusinesses) }}" data-verified-phone="{{ auth()->user()->phone }}" data-listing-id="{{ $listing?->id }}">
        <div class="space-y-5"><div role="alert" data-form-errors class="hidden alert alert-error"></div>
            <fieldset class="panel seller-editor-category" id="listing-category"><legend>Nimani sotmoqchisiz?</legend><p>Taklifingizga mos yo‘nalishni tanlang.</p><div class="seller-editor-category-grid">@foreach (['cars' => ['Avtomobil', 'Yangi, foydalanilgan yoki ijara', 'car-front'], 'parts' => ['Ehtiyot qism', 'Detal, shina va aksessuarlar', 'wrench'], 'moto' => ['Moto', 'Mototsikl, skuter va moped', 'bike']] as $value => [$title, $description, $icon])<label><input type="radio" name="listing_section" value="{{ $value }}" data-listing-section @checked($initial['section'] === $value) @disabled($listing && $initial['section'] !== $value)><span><i data-icon="{{ $icon }}" aria-hidden="true"></i><strong>{{ $title }}</strong><small>{{ $description }}</small><b aria-hidden="true">✓</b></span></label>@endforeach</div></fieldset>
            <div data-schema-fields class="space-y-5"><div class="panel p-6" role="status">Forma yuklanmoqda…</div></div><noscript><p class="alert">E’lon shaklini to‘ldirish uchun JavaScript yoqilgan bo‘lishi kerak.</p></noscript>
        </div>
        <aside class="seller-editor-aside"><section class="panel p-5" id="listing-photos"><h2 class="text-lg font-bold">Suratlar qo‘shing</h2><p class="muted mb-4 mt-2">10 tagacha JPG, PNG yoki WebP. Har biri 5 MB gacha.</p><input type="file" data-photo-upload multiple accept="image/jpeg,image/png,image/webp" aria-label="E’lon rasmlari"><div data-existing-images class="mt-4 grid grid-cols-2 gap-2"></div><p class="seller-editor-tip">Mahsulotni yorug‘ joyda, bir necha tomondan suratga oling.</p></section>
            <section class="panel p-5"><h2 class="text-lg font-bold">E’lonni saqlash</h2><label class="mt-4 flex items-center gap-3 text-sm"><input class="checkbox" type="checkbox" data-submit-moderation checked>Tekshiruvga yuborish</label><p class="muted mt-3">Tekshiruvdan o‘tgach, e’loningiz xaridorlarga ko‘rinadi. Belgini olib tashlasangiz, qoralama saqlanadi.</p><button type="submit" class="btn btn-brand mt-5 w-full" data-save-listing>Saqlash va davom etish</button><a class="seller-editor-cabinet-link" href="{{ route('cabinet') }}">Kabinetga qaytish</a><p class="muted mt-3" data-save-status role="status"></p></section>
            <div class="seller-editor-saved" data-listing-saved hidden role="status">E’loningiz saqlandi. Holatini kabinetingizdan kuzatishingiz mumkin.<a href="{{ route('cabinet') }}">Kabinetda ko‘rish →</a></div>
            <div class="seller-editor-contact-note"><i data-icon="phone" aria-hidden="true"></i><div><strong>Telefon raqamingiz tasdiqlangan</strong><p>{{ auth()->user()->phone }}</p><a href="{{ route('phone.start') }}">Raqamni o‘zgartirish</a></div></div>
        </aside>
    </form>
</div></main>
@endsection
