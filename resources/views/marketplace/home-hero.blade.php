<div class="market-hero-grid">
    <section class="market-hero" data-market-hero data-hero-active="0" aria-label="{{ config('brand.name') }} takliflari">
        <img class="market-hero-reference" src="{{ asset('images/marketplace/reference-hero.webp') }}" width="1017" height="297" alt="" aria-hidden="true" fetchpriority="high" decoding="async">
        <img class="market-hero-image" src="{{ asset('images/marketplace/bukhara-blue-onix.webp') }}" width="2172" height="724" alt="Buxoroning tarixiy me’morchiligi fonidagi ko‘k avtomobil" fetchpriority="high" decoding="async">
        <div class="market-hero-copy"><h1 data-hero-title><span>O‘zingizga</span><span>mosini toping</span></h1><p data-hero-description>Avto, ehtiyot qism va moto — bir joyda</p><a href="{{ route('listings', ['section' => 'cars']) }}" class="btn btn-brand market-hero-action" data-hero-action aria-label="Avtomobillarni ko‘rish"><span>Avtomobillarni ko‘rish</span><i data-icon="chevron-right" aria-hidden="true"></i></a></div>
        <div class="market-hero-dots" role="group" aria-label="Banner tanlash">@foreach (['Avtomobillar', 'Ehtiyot qismlar', 'Moto', 'Arenda'] as $slide)<button type="button" data-hero-slide="{{ $loop->index }}" aria-label="{{ $slide }} banneri" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" class="{{ $loop->first ? 'is-active' : '' }}"></button>@endforeach</div>
    </section>
    <section class="market-fit-panel" aria-labelledby="fit-heading">
        <h2 id="fit-heading">Mashinamga mos detal</h2><p>Avtomobilingizni tanlang va mos ehtiyot qismlarni toping</p>
        <form method="GET" action="{{ route('parts.find') }}" data-fit-finder>
            <label class="market-fit-field"><i data-icon="car-front" aria-hidden="true"></i><span>Marka</span><select name="vehicle_make_id" required data-fit-make aria-label="Avtomobil markasi"><option value="">Marka tanlang</option></select></label>
            <label class="market-fit-field"><i data-icon="car-front" aria-hidden="true"></i><span>Model</span><select name="vehicle_model_id" required disabled data-fit-model aria-label="Avtomobil modeli"><option value="">Model tanlang</option></select></label>
            <label class="market-fit-field"><i data-icon="calendar-days" aria-hidden="true"></i><span>Yil</span><select name="year" required aria-label="Avtomobil yili"><option value="">Yil tanlang</option>@for ($year = now()->year + 1; $year >= 1980; $year--)<option value="{{ $year }}">{{ $year }}</option>@endfor</select></label>
            <button type="submit" class="btn btn-brand market-fit-submit"><i data-icon="search" aria-hidden="true"></i>Mos detallarni topish</button>
            <p class="market-fit-status" data-fit-status role="status"></p>
        </form>
    </section>
</div>
@php
    $homeCategories = [
        ['title' => 'Avtomobillar', 'description' => 'Yangi va foydalanilgan', 'photo' => 'car', 'filters' => ['section' => 'cars']],
        ['title' => 'Shina', 'description' => 'Barcha o‘lchamlar', 'photo' => 'tyre', 'filters' => ['section' => 'parts', 'part_category' => 'tyres']],
        ['title' => 'Fara', 'description' => 'Yorug‘lik tizimlari', 'photo' => 'headlight', 'filters' => ['section' => 'parts', 'part_category' => 'headlights']],
        ['title' => 'Motor', 'description' => 'Dvigatel va qismlar', 'photo' => 'engine', 'filters' => ['section' => 'parts', 'part_category' => 'engine']],
        ['title' => 'Moto', 'description' => 'Skuter, moped, mototsikl', 'photo' => 'moto', 'filters' => ['section' => 'moto']],
        ['title' => 'Avto arenda', 'description' => 'Avtomobillar ichida', 'photo' => 'rental', 'filters' => ['section' => 'cars', 'offer_type' => 'rent']],
    ];
@endphp
<nav class="market-quick-categories" aria-label="Tezkor kategoriyalar">@foreach ($homeCategories as $category)<a class="market-category-tile" href="{{ route('listings', $category['filters']) }}"><span class="market-category-photo market-category-photo--{{ $category['photo'] }}" aria-hidden="true"></span><i class="market-category-arrow" data-icon="chevron-right" aria-hidden="true"></i><strong>{{ $category['title'] }}</strong><span class="market-category-description">{{ $category['description'] }}</span></a>@endforeach</nav>
