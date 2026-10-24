<section class="market-part-offers" aria-labelledby="part-offers-heading">
    <div class="market-part-offers-intro"><h2 id="part-offers-heading">Bir detal — turli do‘konlar narxi</h2><p>Masalan, Chevrolet Onix fara (o‘ng) uchun takliflar</p></div>
    <a class="market-part-offers-all" href="{{ route('listings', ['section' => 'parts', 'part_product_id' => $featuredPart->id]) }}">Barchasini ko‘rish <i data-icon="chevron-right" aria-hidden="true"></i></a>
    <div class="market-part-offer-grid">
        @foreach ($partOffers as $offer)
            @php
                $shop = $offer->business;
                $metadata = $shop->presentation;
                $stock = $offer->partDetails?->stock_quantity;
            @endphp
            <article class="market-shop-offer" data-shop-offer aria-label="{{ $shop->name }} — demo taklif">
                <span class="market-shop-avatar {{ $loop->last ? 'is-neutral' : '' }}" aria-hidden="true">BA</span>
                <div class="market-shop-info"><div class="market-shop-title"><strong>{{ $shop->name }}</strong><span class="market-shop-verified" title="Demo tasdiqlash belgisi"><b aria-hidden="true">✓</b> Tekshirilgan biznes<span class="sr-only"> (demo)</span></span></div><p><span class="market-shop-rating" aria-label="Demo reyting">★</span> {{ number_format($metadata['rating'], 1) }} ({{ $metadata['reviews_count'] }}) <span class="market-shop-divider">|</span> Bor ({{ $stock }} dona) <span class="market-shop-divider">|</span> <span class="market-shop-city">♧ Buxoro</span></p></div>
                <p class="market-shop-price">{{ number_format((float) $offer->price, 0, '.', ' ') }} so‘m</p>
                <a class="btn btn-brand market-shop-action" href="{{ route('listing', $offer) }}" aria-label="{{ $shop->name }} demo taklifini ko‘rish"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 3h3l3 12h11l3-9H6M9 20h.01M18 20h.01"/></svg>Sotib olish</a>
            </article>
        @endforeach
    </div>
</section>
