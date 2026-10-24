@extends('layouts.app')
@section('title', $model->make->name.' '.$model->name.' uchun mos detallar — BuxoroAvto')
@section('content')
<main id="main" class="shell py-8 sm:py-12">
    <a href="{{ route('home') }}" class="text-sm font-semibold text-brand">← Transportni o‘zgartirish</a>
    <p class="eyebrow mt-7">MASHINAMGA MOS DETAL</p>
    <h1 class="page-heading mt-3">{{ $model->make->name }} {{ $model->name }} uchun detallar</h1>
    <p class="muted mt-3">{{ $year }}-yil{{ $selectedVariant ? ' · '.$selectedVariant->name : '' }}. Natijalar katalogdagi transport mosliklari bo‘yicha topildi.</p>

    @if ($variants->isNotEmpty())
        <section class="panel mt-7 p-5 sm:p-6" aria-labelledby="fitment-selection-title">
            <h2 id="fitment-selection-title" class="text-lg font-bold">Moslikni aniqlashtiring</h2>
            <p class="muted mt-2">@if ($variants->count() > 1 && ! $selectedVariant) Bu modelning bir nechta motor / komplektatsiya varianti mavjud. Aniqroq moslik uchun o‘zingiznikini tanlang. @else Detalni olishdan oldin artikul va transport variantini sotuvchi bilan tekshiring. @endif</p>
            <form method="GET" action="{{ route('parts.find') }}" class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end">
                <input type="hidden" name="vehicle_make_id" value="{{ $model->vehicle_make_id }}">
                <input type="hidden" name="vehicle_model_id" value="{{ $model->id }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <label class="field flex-1">Motor / komplektatsiya
                    <select class="select" name="vehicle_variant_id">
                        <option value="">Barcha mos variantlar</option>
                        @foreach ($variants as $variant)
                            <option value="{{ $variant->id }}" @selected($selectedVariant?->id === $variant->id)>{{ $variant->generation->name }} · {{ $variant->name }}{{ $variant->engine_code ? ' · '.$variant->engine_code : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn btn-brand">Aniqlashtirish</button>
            </form>
        </section>
    @endif

    <section class="mt-9" aria-labelledby="fitment-results-title">
        <div class="mb-5 flex flex-wrap items-baseline justify-between gap-3">
            <h2 id="fitment-results-title" class="text-xl font-bold">Katalogdagi mos detallar</h2>
            <p class="muted">{{ $products->total() }} ta detal</p>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($products as $product)
                <article class="panel flex flex-col p-5 sm:p-6" data-fitment-product="{{ $product->id }}">
                    <p class="text-xs font-semibold text-brand">{{ config('marketplace.part_categories.'.$product->category, 'Ehtiyot qism') }}</p>
                    <h3 class="mt-3 text-lg font-bold">{{ $product->title }}</h3>
                    <p class="muted mt-2">{{ $product->manufacturer }} · {{ $product->part_number }}</p>
                    @if ($product->oem_number)<p class="muted">OEM: {{ $product->oem_number }}</p>@endif
                    <p class="mt-5 text-sm font-semibold">{{ $product->offers_count ? $product->offers_count.' ta do‘kon taklifi' : 'Hozircha ochiq do‘kon taklifi yo‘q' }}</p>
                    <a href="{{ route('listings', ['section' => 'parts', 'part_product_id' => $product->id]) }}" class="btn btn-brand mt-5">{{ $product->offers_count ? 'Do‘kon takliflarini ko‘rish' : 'Takliflarni tekshirish' }}</a>
                </article>
            @endforeach
        </div>
        @if ($products->isEmpty())
            <div class="panel px-6 py-12 text-center" role="status">
                <h3 class="text-xl font-bold">{{ $variants->isEmpty() ? 'Bu yil uchun katalogda transport varianti topilmadi' : 'Hozircha katalogda mos detal topilmadi' }}</h3>
                <p class="muted mx-auto mt-3 max-w-xl">{{ $variants->isEmpty() ? 'Model va ishlab chiqarilgan yilni tekshiring. Katalog ma’lumotlari to‘ldirib boriladi.' : 'Bu transport uchun moslik ma’lumoti hali kiritilmagan bo‘lishi mumkin. Sotuvchi bilan artikul bo‘yicha aniqlashtiring.' }}</p>
                <a href="{{ route('listings', ['section' => 'parts']) }}" class="btn btn-brand mt-6">Barcha zapchastlarni ko‘rish</a>
            </div>
        @endif
        <div class="mt-7">{{ $products->links() }}</div>
    </section>
</main>
@endsection
