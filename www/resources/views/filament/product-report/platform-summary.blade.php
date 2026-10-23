@php
    $labels = \App\Stores\Metric::countLabels();
    $rows = $report->summary();
    // Yalnızca en az bir platformda verisi olan metrik sütunları gösterilir.
    $columns = collect($labels)->filter(fn ($label, $metric) => collect($rows)->contains(fn ($row) => isset($row['totals'][$metric])));
    $fmt = fn ($value) => number_format($value, 0, ',', '.');
@endphp
<x-filament-widgets::widget>
    @include('filament.product-report._styles')
    <x-filament::section
        heading="Platform özeti"
        :description="$report->from->format('d.m.Y').' – '.$report->to->format('d.m.Y')"
    >
        @if ($rows === [])
            <p class="kr-empty">Bu ürüne bağlı uygulama yok. "Uygulamaları düzenle" ile platformlardaki uygulamaları ekleyin.</p>
        @else
            <div class="kr-scroll">
                <table class="kr-table">
                    <thead>
                        <tr>
                            <th>Platform</th>
                            @foreach ($columns as $label)
                                <th class="num">{{ $label }}</th>
                            @endforeach
                            <th>Son veri</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>
                                    <span class="kr-dot" style="background: {{ \App\Reports\ProductReport::STORE_COLORS[$row['store']->value] }}"></span>
                                    <strong>{{ $row['store']->label() }}</strong>
                                    <div class="kr-muted">{{ $row['apps'] }}</div>
                                </td>
                                @foreach ($columns as $metric => $label)
                                    <td class="num">{{ isset($row['totals'][$metric]) ? $fmt($row['totals'][$metric]) : '—' }}</td>
                                @endforeach
                                <td>
                                    @if ($row['latest'])
                                        {{ $row['latest'] }}
                                    @else
                                        <span class="kr-muted">Henüz veri yok</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if (count($rows) > 1 && $columns->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td>Toplam</td>
                                @foreach ($columns as $metric => $label)
                                    <td class="num">{{ $fmt(collect($rows)->sum(fn ($row) => $row['totals'][$metric] ?? 0)) }}</td>
                                @endforeach
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
