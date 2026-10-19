@props(['listing', 'private' => false])
@php
    $section = $listing->section->value;
    $details = $listing->relationLoaded($listing->detailRelation()) ? $listing->getRelation($listing->detailRelation()) : null;
    $labels = ['cars' => 'Avtomobil', 'parts' => 'Zapchast', 'moto' => 'Moto'];
    $href = $private ? route('cabinet.listing.edit', $listing) : route('listing', $listing);
@endphp
<article class="panel group overflow-hidden transition-shadow hover:shadow-md">
    <a href="{{ $href }}" class="card-image relative" aria-label="{{ $listing->title }}">
        @if ($listing->coverImage)
            <img src="{{ Storage::disk('public')->url($listing->coverImage->thumbnail_path ?? $listing->coverImage->path) }}" alt="{{ $listing->title }}" width="640" height="480" loading="lazy" decoding="async" class="h-full w-full object-cover">
        @else
            <i data-icon="{{ ['cars' => 'car-front', 'parts' => 'wrench', 'moto' => 'bike'][$section] }}" class="!h-14 !w-14 text-slate-300" aria-hidden="true"></i>
        @endif
        <span class="absolute left-3 top-3 rounded-md bg-white/95 px-2 py-1 text-xs font-semibold">{{ $private ? (['draft' => 'Qoralama', 'pending' => 'Tekshiruvda', 'published' => 'Faol', 'rejected' => 'Rad etilgan', 'sold' => 'Sotilgan', 'archived' => 'Arxiv'][$listing->status->value] ?? $listing->status->value) : ($listing->offer_type === 'rent' ? 'Arenda' : config('marketplace.conditions.'.$listing->condition)) }}</span>
    </a>
    <div class="p-4">
        <p class="mb-2 text-xs text-slate-500">{{ $labels[$section] }}</p>
        <h3 class="truncate font-semibold"><a href="{{ $href }}">{{ $listing->title }}</a></h3>
        @if ($details)<p class="mt-2 truncate text-sm text-slate-500">{{ $section === 'parts' ? ($details->manufacturer ?: config('marketplace.part_categories.'.$details->category)) : implode(' · ', array_filter([$details->year, $details->mileage_km !== null ? number_format($details->mileage_km).' km' : null])) }}</p>@endif
        <p class="mt-4 text-xl font-bold">{{ number_format((float) $listing->price, (float) $listing->price === floor((float) $listing->price) ? 0 : 2, '.', ' ') }} <span class="text-sm font-medium">{{ $listing->currency === 'UZS' ? 'so‘m' : 'USD' }}{{ $listing->offer_type === 'rent' ? ' / '.mb_strtolower(config('marketplace.rental_periods.'.$listing->rental_period)) : '' }}</span></p>
        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-500"><span>{{ $listing->city }}</span><span>{{ $listing->published_at?->format('d.m.Y') }}</span></div>
    </div>
</article>
