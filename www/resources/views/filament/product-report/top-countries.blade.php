@php
    $countries = $report->topCountries($metric);
    $max = collect($countries)->max('value') ?: 1;
    $name = function (string $code) {
        try {
            return \Symfony\Component\Intl\Countries::getName($code, 'tr');
        } catch (\Throwable) {
            return $code;
        }
    };
@endphp
<x-filament-widgets::widget>
    @include('filament.product-report._styles')
    <x-filament::section heading="En çok {{ mb_strtolower(\App\Stores\Metric::countLabels()[$metric]) }} gelen ülkeler">
        @if ($countries === [])
            <p class="kr-empty">Bu dönem için ülke kırılımlı veri yok.</p>
        @else
            <table class="kr-table">
                <tbody>
                    @foreach ($countries as $row)
                        <tr>
                            <td style="width: 40%">{{ $name($row['country']) }}</td>
                            <td><div class="kr-bar" style="width: {{ max(4, round($row['value'] / $max * 100)) }}%"></div></td>
                            <td class="num" style="width: 15%">{{ number_format($row['value'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
