@if ($sidebar ?? false)
    <div class="seller-account-summary"><span class="seller-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><div><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->seller_type === 'shop' ? 'Biznes kabineti' : 'Shaxsiy kabinet' }}</span></div></div>
    <nav class="seller-sidebar-nav" aria-label="Kabinet menyusi">
        <a href="{{ route('cabinet') }}" class="is-active" aria-current="page"><i data-icon="menu" aria-hidden="true"></i>Mening e’lonlarim<span>{{ $counts->sum() }}</span></a>
        <a href="{{ route('cabinet') }}#seller-businesses"><i data-icon="car-front" aria-hidden="true"></i>Bizneslarim</a>
        <a href="{{ route('cabinet') }}#seller-notifications"><i data-icon="image" aria-hidden="true"></i>Bildirishnomalar @if ($notifications->whereNull('read_at')->count())<span>{{ $notifications->whereNull('read_at')->count() }}</span>@endif</a>
        <a href="{{ route('seller.profile') }}"><i data-icon="user-round" aria-hidden="true"></i>Profil sozlamalari</a>
        @if (auth()->user()->is_admin)<a href="{{ route('admin') }}"><i data-icon="sliders-horizontal" aria-hidden="true"></i>Moderatsiya</a>@endif
    </nav>
    <div class="seller-sidebar-bottom"><a href="{{ route('home') }}"><span aria-hidden="true">←</span> Marketplace’ga qaytish</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Chiqish<span aria-hidden="true">↗</span></button></form></div>
@else
    <div class="seller-subnav"><nav aria-label="Kabinet menyusi"><a href="{{ route('cabinet') }}" @if(request()->routeIs('cabinet')) aria-current="page" @endif>Kabinet</a><a href="{{ route('cabinet.listing.create') }}">Yangi e’lon</a><a href="{{ route('seller.profile') }}" @if(request()->routeIs('seller.profile')) aria-current="page" @endif>Profil sozlamalari</a>@if (auth()->user()->is_admin)<a href="{{ route('admin') }}">Moderatsiya</a>@endif<a href="{{ route('home') }}">Marketplace</a></nav><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Chiqish ↗</button></form></div>
@endif
