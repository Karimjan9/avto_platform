@props(['listing', 'private' => false])
@php
    $section = $listing->section->value;
    $details = $listing->relationLoaded($listing->detailRelation()) ? $listing->getRelation($listing->detailRelation()) : null;
    $href = $private ? route('cabinet.listing.edit', $listing) : route('listing', $listing);
    $photo = $listing->coverImage ? Storage::disk('public')->url($listing->coverImage->thumbnail_path ?? $listing->coverImage->path) : null;
    $price = number_format((float) $listing->price, (float) $listing->price === floor((float) $listing->price) ? 0 : 2, '.', ' ').' '.($listing->currency === 'UZS' ? 'so‘m' : 'USD').($listing->offer_type === 'rent' ? ' / '.mb_strtolower(config('marketplace.rental_periods.'.$listing->rental_period)) : '');
    $fuelLabels = ['gasoline' => 'Benzin', 'diesel' => 'Dizel', 'electric' => 'Elektr', 'hybrid' => 'Gibrid', 'lpg' => 'Propan', 'cng' => 'Metan'];
    $gearLabels = ['manual' => 'Mexanika', 'automatic' => 'Avtomat', 'cvt' => 'Variator', 'robotized' => 'Robot', 'semi_automatic' => 'Yarim avtomat'];
    $presentation = $listing->presentation ?? app(\App\Services\MarketplacePresentation::class)->listingMetadata($listing);
    $isDemo = $presentation['is_demo'] ?? false;
    if ($isDemo && $listing->coverImage) $photo = Storage::disk('public')->url($listing->coverImage->path);
@endphp
<article class="listing-tile" @if ($isDemo) data-demo-listing aria-label="{{ $listing->title }} — demo e’lon" @endif>
    <a href="{{ $href }}" class="listing-tile-image" aria-label="{{ $listing->title }}">
        @if ($photo)<img src="{{ $photo }}" alt="{{ $listing->title }}" width="640" height="480" loading="lazy" decoding="async">
        @else<i data-icon="{{ ['cars' => 'car-front', 'parts' => 'wrench', 'moto' => 'bike'][$section] }}" aria-hidden="true"></i>@endif
        @if ($private || $listing->offer_type === 'rent')<span class="listing-tile-status">{{ $private ? (['draft' => 'Qoralama', 'pending' => 'Tekshiruvda', 'published' => 'Faol', 'rejected' => 'Rad etilgan', 'sold' => 'Sotilgan', 'archived' => 'Arxiv'][$listing->status->value]) : 'Arenda' }}</span>@endif
        @if (!$private && isset($presentation['badge_type']))<span class="listing-tile-business-badge {{ $presentation['badge_type'] === 'sponsored' ? 'is-sponsored' : '' }}" title="Demo ko‘rinishidagi belgi"><span aria-hidden="true">{{ $presentation['badge_type'] === 'sponsored' ? '↟' : '✓' }}</span>{{ $presentation['badge_type'] === 'sponsored' ? 'Reklama' : 'Tekshirilgan biznes' }}<span class="sr-only"> (demo)</span></span>@endif
    </a>
    @unless ($private)<button type="button" class="listing-tile-save" data-save-card data-saved-item="{{ json_encode(['id' => $listing->id, 'title' => $listing->title, 'url' => route('listing', $listing), 'image' => $photo, 'price' => $price]) }}" aria-label="{{ $listing->title }} e’lonini saqlash" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg></button>@endunless
    <div class="listing-tile-body">
        <h3><a href="{{ $href }}">{{ $listing->title }}</a></h3>
        <p class="listing-tile-specs">@if ($section === 'parts'){{ implode(' · ', array_filter([config('marketplace.part_origins.'.$details?->origin), config('marketplace.conditions.'.$listing->condition), $isDemo ? null : $details?->manufacturer])) }}@else{{ implode(' · ', array_filter([$details?->year, $details?->mileage_km !== null ? number_format($details->mileage_km, 0, '.', ' ').' km' : null, $gearLabels[$details?->transmission] ?? null, $fuelLabels[$details?->fuel_type] ?? null])) }}@endif</p>
        <p class="listing-tile-location"><i data-icon="map-pin" aria-hidden="true"></i>{{ $listing->city }}</p>
        <p class="listing-tile-price">{{ $price }}</p>
    </div>
</article>
