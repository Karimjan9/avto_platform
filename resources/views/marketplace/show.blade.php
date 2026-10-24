@extends('layouts.app')
@section('title', $listing->title.' — '.config('brand.name'))
@section('description', Str::limit($listing->description, 155))
@if ($preview ?? false) @section('robots', 'noindex,nofollow') @endif
@section('content')
@php
    $isDemo = app(\App\Services\MarketplacePresentation::class)->isDemoListing($listing);
    $details = $listing->getRelation($listing->detailRelation());
    $fields = app(\App\Services\ListingFormSchema::class)->fields(['section' => $listing->section->value, 'condition' => $listing->condition, 'offer_type' => $listing->offer_type, 'details' => $details?->toArray() ?? []]);
@endphp
<main id="main" class="shell pt-8">
    <a href="{{ route('listings', ['section' => $listing->section->value]) }}" class="muted">← E’lonlarga qaytish</a>
    @if ($isDemo)<section class="panel mt-5 border-brand/20 bg-violet-50 p-5" data-demo-notice role="status"><h2 class="font-bold text-brand">Namoyish uchun test e’lon</h2><p class="muted mt-2">Ushbu e’lon dizaynni ko‘rsatish uchun yaratilgan. Ma’lumotlar haqiqiy savdo taklifi emas.</p></section>@endif
    <div class="mt-6 grid gap-7 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div><div class="panel overflow-hidden">
            @if ($listing->images->isNotEmpty())<div data-gallery class="grid grid-cols-2 gap-1">
                @foreach ($listing->images as $image)<a href="{{ Storage::disk('public')->url($image->path) }}" class="{{ $loop->first ? 'col-span-2' : '' }} bg-slate-100" @if ($image->width && $image->height) data-pswp-width="{{ $image->width }}" data-pswp-height="{{ $image->height }}" @endif target="_blank" rel="noopener"><img src="{{ Storage::disk('public')->url($isDemo ? $image->path : ($image->display_path ?? $image->path)) }}" alt="{{ $listing->title }} — {{ $loop->iteration }}-rasm" width="1200" height="900" class="aspect-[4/3] w-full object-cover" @if (!$loop->first) loading="lazy" @else fetchpriority="high" @endif decoding="async"></a>@endforeach
            </div>@else<div class="flex aspect-[4/3] items-center justify-center bg-slate-100"><i data-icon="image" class="!h-20 !w-20 text-slate-300" aria-hidden="true"></i><span class="sr-only">Rasm yuklanmagan</span></div>@endif
        </div>
        <section class="panel mt-6 p-6"><h2 class="text-xl font-bold">Asosiy ma’lumotlar</h2><dl class="mt-5 grid gap-x-8 gap-y-4 sm:grid-cols-2">
            @foreach ($fields as $path => $field)
                @if (str_starts_with($path, 'details.') && !str_contains($path, '*') && $field['visible'])
                    @php $value = data_get($details, substr($path, 8)); @endphp
                    @if ($value !== null && $value !== '' && !is_array($value))<div class="flex justify-between gap-3 border-b border-slate-100 pb-3 text-sm"><dt class="text-slate-500">{{ $field['label'] }}</dt><dd class="text-right font-medium">{{ is_bool($value) ? ($value ? 'Ha' : 'Yo‘q') : ($field['options'][$value] ?? $value) }}</dd></div>@endif
                @endif
            @endforeach
        </dl></section>
        <section class="panel mt-6 p-6"><h2 class="text-xl font-bold">Tavsif</h2><p class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-600">{{ $listing->description }}</p></section>
        </div>
        <aside class="space-y-5"><section class="panel p-6"><div class="flex flex-wrap gap-2"><span class="badge badge-outline">{{ config('marketplace.conditions.'.$listing->condition) }}</span><span class="badge badge-outline">{{ config('marketplace.offer_types.'.$listing->offer_type) }}</span>@if ($listing->promotion?->is_active && $listing->promotion->starts_at->lte(now()) && $listing->promotion->ends_at->gt(now()))<span class="badge badge-warning">Reklama</span>@endif</div><h1 class="mt-5 text-2xl font-bold tracking-tight">{{ $listing->title }}</h1><p class="muted mt-3">{{ $listing->city }}{{ $listing->region ? ', '.$listing->region : '' }}</p><p class="mt-6 text-3xl font-bold">{{ number_format((float) $listing->price, 0, '.', ' ') }} <span class="text-lg">{{ $listing->currency === 'UZS' ? 'so‘m' : 'USD' }}</span></p>@if ($listing->offer_type === 'rent')<p class="muted">Bir {{ mb_strtolower(config('marketplace.rental_periods.'.$listing->rental_period)) }} uchun</p>@endif @if ($listing->is_negotiable)<p class="mt-2 text-sm text-emerald-800">Narxi kelishiladi</p>@endif
            @if ($isDemo)<p class="muted mt-6">Bu test e’londa aloqa va savdo amallari mavjud emas.</p>@else<a href="tel:{{ $listing->contact_phone }}" class="btn btn-brand mt-6 w-full"><i data-icon="phone" aria-hidden="true"></i>{{ $listing->contact_phone }}</a>@endif<p class="muted mt-4">Sotuvchi: {{ $listing->business?->name ?? $listing->user->name }}</p>
            @if (\App\Services\TrustBadges::has($listing->user->verifications, 'phone', $listing->user->phone) && $listing->contact_phone === $listing->user->phone)<p class="mt-3 text-sm text-emerald-800">✓ Telefon tasdiqlangan</p>@endif
            @if (\App\Services\TrustBadges::has($listing->verifications, 'vehicle_inspection'))<p class="mt-2 text-sm text-emerald-800">✓ Transport ko‘rikdan o‘tgan</p>@endif
        </section>
        @if (!$isDemo && $listing->offer_type === 'rent' && !($preview ?? false))<section class="panel p-6" data-rental-picker data-listing="{{ $listing->id }}" data-driver="{{ $listing->rentalDetails?->driver_option }}"><h2 class="text-lg font-bold">Arenda muddatini tanlang</h2><label class="field mt-4">Boshlanish sanasi<input class="input" data-rental-start type="date" required></label><label class="field mt-4">Tugash sanasi<input class="input" data-rental-end type="date" required></label><label class="field mt-4">Haydovchi<select class="select" data-rental-driver>@if ($listing->rentalDetails?->driver_option !== 'with_driver')<option value="without_driver">Haydovchisiz</option>@endif @if ($listing->rentalDetails?->driver_option !== 'without_driver')<option value="with_driver">Haydovchi bilan</option>@endif</select></label><p class="muted mt-3" data-availability role="status">Sanalarni tanlab bandlikni tekshiring.</p>@auth<button type="button" class="btn btn-ink mt-4 w-full" data-rental-book>Bron so‘rovi yuborish</button>@else<a href="{{ route('login') }}" class="btn btn-ink mt-4 w-full">Bron qilish uchun kirish</a>@endauth</section>@endif
        </aside>
    </div>
</main>
@endsection
