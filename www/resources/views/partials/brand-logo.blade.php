{{-- Kalehost yatay logosu; koyu temada beyaz varyant. $height piksel cinsinden. --}}
<picture style="display:inline-flex">
    <source srcset="{{ asset('images/kalehost_yatay_beyaz.svg') }}" media="(prefers-color-scheme: dark)">
    <img src="{{ asset('images/kalehost_logo.svg') }}" alt="Kale Host" height="{{ $height ?? 36 }}" style="height:{{ $height ?? 36 }}px;width:auto;display:block">
</picture>
