<a href="{{ $logoHref ?? route('home') }}" class="app-brand app-brand--{{ $logoVariant ?? 'header' }}" aria-label="NF C++ CodeQuest home">
    <img src="{{ asset('images/branding/nf-cpp-codequest-logo.png') }}" alt="NF C++ CodeQuest" width="1997" height="787" decoding="async">
    @if(!empty($logoCaption))<span class="app-brand__caption">{{ $logoCaption }}</span>@endif
</a>
