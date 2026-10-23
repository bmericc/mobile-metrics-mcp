{{--
    Kalehost yatay logosu ve "Metrics" yazısı: iki ayrı SVG, yan yana.
    $height: yükseklik (px). $metrics: "Metrics" görselini ekle.
    $variant: 'auto' (işletim sistemi temasına göre) | 'white' | 'black'. Filament kendi tema anahtarını
    kullandığı için orada 'black' / 'white' açıkça verilir (brandLogo / darkModeBrandLogo).

    "Metrics" SVG'si (kalehost_metrics_yazi*.svg) logoyla aynı ölçekte üretildi: yükseklik 260 birim,
    taban çizgisi y=210, büyük harf yüksekliği 95 — iki görsel aynı yükseklikte gösterilince harfler
    birebir aynı boyutta ve aynı hizada durur. Yazı tipine bağımlı değil.
--}}
@php
    $height = $height ?? 36;
    $metrics = $metrics ?? true;
    $variant = $variant ?? 'auto';
    $gap = round($height * 52 / 260, 1);
    $images = [
        'logo' => ['images/kalehost_logo.svg', 'images/kalehost_yatay_beyaz.svg', 'Kale Host'],
        'metrics' => ['images/kalehost_metrics_yazi.svg', 'images/kalehost_metrics_yazi_beyaz.svg', 'Metrics'],
    ];
    if (! $metrics) {
        unset($images['metrics']);
    }
@endphp
<span style="display:inline-flex;align-items:flex-start;gap:{{ $gap }}px">
    @foreach ($images as [$black, $white, $alt])
        @if ($variant === 'auto')
            <picture style="display:block">
                <source srcset="{{ asset($white) }}" media="(prefers-color-scheme: dark)">
                <img src="{{ asset($black) }}" alt="{{ $alt }}" style="height:{{ $height }}px;width:auto;display:block">
            </picture>
        @else
            <img src="{{ asset($variant === 'white' ? $white : $black) }}" alt="{{ $alt }}" style="height:{{ $height }}px;width:auto;display:block">
        @endif
    @endforeach
</span>
