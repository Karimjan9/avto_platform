@extends('layouts.app')
@section('title', 'Arenda kalendari — BuxoroAvto')
@section('robots', 'noindex,nofollow')
@section('content')
<main id="main" class="shell pt-8">@include('cabinet.nav')<p class="eyebrow">ARENDA</p><h1 class="page-heading mt-3">{{ $listing->title }}</h1><p class="muted mt-3">Bandlik kalendari. Vaqtlar Asia/Tashkent bo‘yicha ko‘rsatiladi.</p><section class="panel mt-7 p-4 sm:p-6" data-rental-calendar data-listing="{{ $listing->id }}"><div data-calendar-view></div><p data-calendar-status role="status" class="muted mt-4">Bandlik yuklanmoqda…</p></section></main>
@endsection
