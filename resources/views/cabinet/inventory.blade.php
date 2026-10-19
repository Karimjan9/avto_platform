@extends('layouts.app')
@section('title', $business->name.' — Ombor')
@section('robots', 'noindex,nofollow')
@section('content')
<main id="main" class="shell pt-8">@include('cabinet.nav')<p class="eyebrow">BIZNES KABINETI</p><h1 class="page-heading mt-3">{{ $business->name }}</h1><p class="muted mt-3">Mahsulotlar va ombor qoldig‘i. Qoldiq o‘zgartirilganda serverdagi joriy versiya tekshiriladi.</p>
    <section class="panel mt-7 overflow-hidden p-5" data-inventory data-business="{{ $business->id }}" data-can-stock="{{ $canStock ? '1' : '0' }}"><div data-inventory-grid aria-label="Ombordagi mahsulotlar jadvali"></div><p class="muted mt-4" role="status" data-inventory-status>Mahsulotlar yuklanmoqda…</p><button type="button" class="btn btn-ghost mt-3" data-inventory-more hidden>Yana yuklash</button></section>
    @if ($canImport)<section class="panel mt-6 p-6" data-csv-import data-business="{{ $business->id }}"><h2 class="text-xl font-bold">CSV orqali mahsulot qo‘shish</h2><p class="muted mt-2">Avval namunani yuklab oling. Fayl tarkibini ko‘rib, keyin importni boshlang. Har 50 qator alohida saqlanadi.</p><button type="button" data-csv-template class="btn btn-ghost mt-4">CSV namunasini yuklab olish</button><label class="field mt-4">CSV fayl<input type="file" accept=".csv,text/csv" data-csv-file class="input" /></label><div class="mt-4 overflow-auto" data-csv-preview></div><p class="muted mt-3" data-csv-status role="status"></p><button type="button" data-csv-save class="btn btn-brand mt-4" disabled>Tekshirilgan qatorlarni import qilish</button></section>@endif
</main>
@endsection
