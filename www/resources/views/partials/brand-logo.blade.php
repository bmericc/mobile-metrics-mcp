{{--
    Text logo: the project name followed by the version number.
    $height: line height (px). $variant: 'auto' (follows the surrounding text colour) | 'white' | 'black'.
    Filament uses its own theme switch, so it passes 'black' / 'white' explicitly
    (brandLogo / darkModeBrandLogo).
--}}
@php
    $height = $height ?? 36;
    $variant = $variant ?? 'auto';
    $color = ['white' => '#ffffff', 'black' => '#111827'][$variant] ?? 'currentColor';
    $size = round($height * 0.55);
@endphp
<span style="display:inline-flex;align-items:baseline;gap:.35em;height:{{ $height }}px;line-height:{{ $height }}px;color:{{ $color }};font-size:{{ $size }}px;font-weight:800;letter-spacing:-.01em;white-space:nowrap">
    <span>Mobile Metrics MCP</span>
    <span style="font-size:.6em;font-weight:600;opacity:.65">{{ \App\AppVersion::CURRENT }}</span>
</span>
