@extends('layouts.app')
@section('title', ($listing ? 'E’lonni tahrirlash' : 'Yangi e’lon').' — BuxoroAvto')
@section('robots', 'noindex,nofollow')
@section('content')
@php $initial = $listing ? (new \App\Http\Resources\ListingResource($listing))->resolve(request()) : ['section' => 'cars', 'condition' => 'used', 'offer_type' => 'sale', 'currency' => 'UZS', 'city' => 'Buxoro', 'details' => [], 'deal_terms' => ['cash'], 'submit' => false]; @endphp
<main id="main" class="shell pt-8">@include('cabinet.nav')<p class="eyebrow">E’LONLAR</p><h1 class="page-heading mt-3">{{ $listing ? 'E’lonni tahrirlash' : 'Yangi e’lon' }}</h1><p class="muted mt-3">Yo‘nalishni tanlang. Kerakli maydonlar tanlovingizga qarab ochiladi.</p>
    <form class="mt-7 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]" data-listing-editor data-initial="{{ json_encode($initial) }}" data-businesses="{{ json_encode($businesses) }}" data-listing-id="{{ $listing?->id }}">
        <div class="space-y-5"><div role="alert" data-form-errors class="hidden alert alert-error"></div><div data-schema-fields class="space-y-5"><div class="panel p-6" role="status">Forma yuklanmoqda…</div></div><noscript><p class="alert">E’lon shaklini to‘ldirish uchun JavaScript yoqilgan bo‘lishi kerak.</p></noscript></div>
        <aside class="space-y-5"><section class="panel p-5"><h2 class="text-lg font-bold">Rasmlar</h2><p class="muted mb-4 mt-2">10 tagacha JPG, PNG yoki WebP. Har biri 5 MB gacha.</p><input type="file" data-photo-upload multiple accept="image/jpeg,image/png,image/webp" aria-label="E’lon rasmlari"><div data-existing-images class="mt-4 grid grid-cols-2 gap-2"></div><p class="muted mt-3">Rasmlar e’lon saqlangandan keyin yuklanadi.</p></section><section class="panel p-5"><h2 class="text-lg font-bold">Saqlash</h2><label class="mt-4 flex items-center gap-3 text-sm"><input class="checkbox" type="checkbox" data-submit-moderation>Moderatsiyaga yuborish</label><p class="muted mt-3">Belgilamasangiz, e’lon qoralama sifatida saqlanadi.</p><button type="submit" class="btn btn-brand mt-5 w-full" data-save-listing>Saqlash</button><p class="muted mt-3" data-save-status role="status"></p></section></aside>
    </form>
</main>
@endsection
