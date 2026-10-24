@extends('layouts.app')
@section('title', ($editing ? 'Profil sozlamalari' : 'Sotuvchi profili').' — BuxoroAvto')
@section('robots', 'noindex,nofollow')
@section('local-form-errors', '1')
@section('content')
@php
    $user = auth()->user();
    $selectedType = old('seller_type', $user->seller_type ?? ($business ? 'shop' : 'individual'));
    $selectedSections = old('sections', $business?->sectionCodes() ?? ['parts']);
@endphp
<main id="main" class="shell seller-profile-page">
    @if ($editing) @include('cabinet.nav') @else
        <a href="{{ route('cabinet') }}" class="seller-back-link"><span aria-hidden="true">←</span> Kabinetga qaytish</a>
    @endif
    <div class="seller-profile-grid">
        <aside class="seller-onboarding-intro">
            <span class="seller-kicker">{{ $editing ? 'SHAXSIY MA’LUMOTLAR' : 'BIRINCHI QADAM TAYYOR' }}</span>
            <div class="seller-profile-symbol" aria-hidden="true"><i data-icon="user-round"></i></div>
            <h1>{{ $editing ? 'Profilingizni boshqaring' : 'Siz bilan tanishamiz' }}</h1>
            <p>{{ $editing ? 'Xaridorlar sizni tanishi va bog‘lanishi uchun ma’lumotlaringizni yangilab turing.' : 'Telefoningiz tasdiqlandi. E’lon joylashtirish uchun o‘zingiz yoki do‘koningiz haqida qisqacha ma’lumot kiriting.' }}</p>
            <div class="seller-verified-number"><span aria-hidden="true">✓</span><div><strong>{{ $user->phone }}</strong><small>Tasdiqlangan telefon raqami</small></div></div>
            <ol class="seller-setup-steps" aria-label="Profilni tayyorlash bosqichlari">
                <li class="is-complete"><span aria-hidden="true">✓</span> Telefonni tasdiqlash</li>
                <li class="is-current"><span aria-hidden="true">2</span> Sotuvchi ma’lumotlari</li>
                <li><span aria-hidden="true">3</span> Birinchi e’lonni joylashtirish</li>
            </ol>
            <p class="seller-privacy-note">Telefon raqamini almashtirish uchun yangi raqamni SMS orqali tasdiqlash kerak. Do‘konlar odatiy tekshiruvdan o‘tadi.</p>
        </aside>
        <section class="seller-profile-form-panel" aria-labelledby="seller-form-heading">
            <div class="seller-panel-heading"><h2 id="seller-form-heading">{{ $editing ? 'Profil sozlamalari' : 'Qanday sotuvchi sifatida ishlaysiz?' }}</h2><p>Yulduzcha bilan belgilangan maydonlarni to‘ldiring.</p></div>
            @if (session('status'))<p class="seller-success" role="status">{{ session('status') }}</p>@endif
            @if ($errors->any())<div class="seller-error" role="alert"><strong>Ma’lumotlarni tekshiring</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="POST" action="{{ route($editing ? 'seller.profile.update' : 'seller.onboarding.store') }}" class="seller-profile-form" x-data="{ sellerType: @js($selectedType), directions: @js(is_array($selectedSections) ? $selectedSections : []) }" data-seller-profile-form>
                @csrf
                @if ($editing) @method('PATCH') @endif
                <fieldset class="seller-type-options"><legend class="sr-only">Sotuvchi turi</legend>
                    <label class="seller-type-card" :class="{ 'is-selected': sellerType === 'individual' }"><input type="radio" name="seller_type" value="individual" x-model="sellerType" @checked($selectedType === 'individual')><span class="seller-type-icon" aria-hidden="true"><i data-icon="user-round"></i></span><span><strong>Jismoniy shaxs</strong><small>Shaxsiy avtomobil yoki buyumlarimni sotaman</small></span></label>
                    <label class="seller-type-card" :class="{ 'is-selected': sellerType === 'shop' }"><input type="radio" name="seller_type" value="shop" x-model="sellerType" @checked($selectedType === 'shop')><span class="seller-type-icon" aria-hidden="true"><i data-icon="car-front"></i></span><span><strong>Do‘kon yoki avtosalon</strong><small>Biznes nomidan savdo qilaman</small></span></label>
                </fieldset>
                <div class="seller-form-row">
                    <label class="seller-field" for="seller-name"><span>Ism va familiya <b aria-hidden="true">*</b></span><input id="seller-name" name="name" value="{{ old('name', $user->seller_onboarded_at ? $user->name : ($user->name === 'Foydalanuvchi' ? '' : $user->name)) }}" required minlength="2" maxlength="120" autocomplete="name" placeholder="Ismingizni kiriting" @error('name') aria-invalid="true" @enderror></label>
                    <label class="seller-field" for="seller-city"><span>Shahar yoki tuman <b aria-hidden="true">*</b></span><input id="seller-city" name="city" value="{{ old('city', $user->city ?? $business?->city) }}" required minlength="2" maxlength="100" autocomplete="address-level2" placeholder="Masalan, Buxoro" @error('city') aria-invalid="true" @enderror></label>
                </div>
                <fieldset class="seller-business-fields" x-show="sellerType === 'shop'" :disabled="sellerType !== 'shop'" x-cloak>
                    <legend>Do‘kon ma’lumotlari</legend>
                    @if ($business)<p class="seller-business-note">Sizga tegishli {{ $business->name }} biznesi yangilanadi. Boshqa foydalanuvchilarning bizneslari o‘zgarmaydi.</p>@endif
                    <div class="seller-form-row">
                        <label class="seller-field" for="seller-business-name"><span>Do‘kon yoki salon nomi <b aria-hidden="true">*</b></span><input id="seller-business-name" name="business_name" value="{{ old('business_name', $business?->name) }}" :required="sellerType === 'shop'" minlength="2" maxlength="160" autocomplete="organization" placeholder="Biznesingiz nomi"></label>
                        <label class="seller-field" for="seller-business-type"><span>Asosiy biznes turi <b aria-hidden="true">*</b></span><select id="seller-business-type" name="business_type" :required="sellerType === 'shop'" @change="const section = { car_dealership: 'cars', parts_store: 'parts', moto_dealership: 'moto' }[$event.target.value]; if (section &amp;&amp; !directions.includes(section)) directions.push(section)">@foreach (\App\Enums\BusinessType::cases() as $type)<option value="{{ $type->value }}" @selected(old('business_type', $business?->type->value ?? 'parts_store') === $type->value)>{{ $type->label() }}</option>@endforeach</select></label>
                    </div>
                    <fieldset class="seller-directions"><legend>Savdo yo‘nalishlari <b aria-hidden="true">*</b></legend><p>Asosiy biznes turiga mos yo‘nalishni ham belgilang.</p><div>@foreach (['cars' => 'Avtomobillar', 'parts' => 'Ehtiyot qismlar', 'moto' => 'Moto'] as $section => $label)<label><input type="checkbox" name="sections[]" value="{{ $section }}" x-model="directions" @checked(in_array($section, is_array($selectedSections) ? $selectedSections : [], true))>{{ $label }}</label>@endforeach</div></fieldset>
                    <label class="seller-field" for="seller-address"><span>Do‘kon manzili <b aria-hidden="true">*</b></span><input id="seller-address" name="address" value="{{ old('address', $business?->address) }}" :required="sellerType === 'shop'" minlength="3" maxlength="255" autocomplete="street-address" placeholder="Ko‘cha va uy raqami"></label>
                    <label class="seller-field" for="seller-description"><span>Do‘kon haqida</span><textarea id="seller-description" name="description" rows="4" maxlength="5000" placeholder="Mahsulotlar va xizmatlaringiz haqida qisqacha yozing">{{ old('description', $business?->description) }}</textarea></label>
                    <p class="seller-business-note"><span aria-hidden="true">ⓘ</span> Aloqa raqami: <strong>{{ $user->phone }}</strong>. Do‘kon tekshiruvga yuboriladi; tasdiqlash belgisi avtomatik berilmaydi.</p>
                </fieldset>
                <div class="seller-form-footer"><p>Ma’lumotlaringizni keyin ham profil sozlamalarida yangilashingiz mumkin.</p><button type="submit" class="seller-primary">{{ $editing ? 'O‘zgarishlarni saqlash' : 'Davom etish' }}<span aria-hidden="true">→</span></button></div>
            </form>
        </section>
    </div>
</main>
@endsection
