{{--
    Text logo: APP_NAME (default "Mobile Metrics MCP") in Sora Bold (SIL OFL 1.1, public/fonts/brand).
    $height: line height (px). $variant: 'auto' (follows the surrounding text colour) | 'white' | 'black'.
    Filament uses its own theme switch, so it passes 'black' / 'white' explicitly
    (brandLogo / darkModeBrandLogo).
--}}
@php
    $height = $height ?? 36;
    $variant = $variant ?? 'auto';
    $color = ['white' => '#ffffff', 'black' => '#111827'][$variant] ?? 'currentColor';
    $size = round($height * 0.5);
@endphp
<style>
    @font-face { font-family: 'Sora'; font-style: normal; font-weight: 700; font-display: swap; src: url('{{ asset('fonts/brand/sora-700.woff2') }}') format('woff2'); }
    .brand-wordmark { display: inline-block; font-family: 'Sora', ui-sans-serif, system-ui, sans-serif; font-weight: 700; letter-spacing: -.02em; white-space: nowrap; }
</style>
<span class="brand-wordmark" style="height:{{ $height }}px;line-height:{{ $height }}px;color:{{ $color }};font-size:{{ $size }}px">{{ config('app.name') }}</span>
