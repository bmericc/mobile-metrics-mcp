{{--
    Kalehost yatay logosu + isteğe bağlı "Metrics" yazısı.
    $height: logo yüksekliği (px). $metrics: "Metrics" yazısını ekle.
    $variant: 'auto' (işletim sistemi temasına göre) | 'white' | 'black'. Filament kendi tema anahtarını
    kullandığı için orada 'black' / 'white' açıkça verilir (brandLogo / darkModeBrandLogo).

    Ölçüler logo SVG'sinden (viewBox 760x260) ölçüldü: "KaleHost" büyük harf yüksekliği 95 birim (%36.5),
    yazı tabanı üstten 210 birim (%80.8), kelime logonun sağ kenarına dayanıyor. "Metrics" aynı büyük harf
    yüksekliği ve aynı taban çizgisiyle yazılır; system-ui'de büyük harf yüksekliği ~0.71em.
--}}
@php
    $height = $height ?? 36;
    $metrics = $metrics ?? true;
    $variant = $variant ?? 'auto';
    $fontSize = round($height * 0.365 / 0.71, 1);
    $padBottom = round($height * 0.192 - $fontSize * 0.21, 1);
    $gap = round($height * 0.12, 1);
@endphp
<span style="display:inline-flex;align-items:flex-end;gap:{{ $gap }}px;line-height:1">
    @if ($variant === 'white')
        <img src="{{ asset('images/kalehost_yatay_beyaz.svg') }}" alt="Kale Host" style="height:{{ $height }}px;width:auto;display:block">
    @elseif ($variant === 'black')
        <img src="{{ asset('images/kalehost_logo.svg') }}" alt="Kale Host" style="height:{{ $height }}px;width:auto;display:block">
    @else
        <picture style="display:block">
            <source srcset="{{ asset('images/kalehost_yatay_beyaz.svg') }}" media="(prefers-color-scheme: dark)">
            <img src="{{ asset('images/kalehost_logo.svg') }}" alt="Kale Host" style="height:{{ $height }}px;width:auto;display:block">
        </picture>
    @endif
    @if ($metrics)
        <span style="font:800 {{ $fontSize }}px/1 ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;letter-spacing:-0.01em;padding-bottom:{{ max($padBottom, 0) }}px;color:{{ ['white' => '#fff', 'black' => '#000'][$variant] ?? 'inherit' }}">Metrics</span>
    @endif
</span>
