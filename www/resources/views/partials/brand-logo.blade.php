{{--
    Text logo: the word "Metrics".
    $height: line height (px). $variant: 'auto' (follows the surrounding text colour) | 'white' | 'black'.
    Filament uses its own theme switch, so it passes 'black' / 'white' explicitly
    (brandLogo / darkModeBrandLogo).
--}}
@php
    $height = $height ?? 36;
    $variant = $variant ?? 'auto';
    $color = ['white' => '#ffffff', 'black' => '#111827'][$variant] ?? 'currentColor';
    $size = round($height * 0.6);
@endphp
<span style="display:inline-block;height:{{ $height }}px;line-height:{{ $height }}px;color:{{ $color }};font-size:{{ $size }}px;font-weight:800;letter-spacing:-.01em;white-space:nowrap">Metrics</span>
