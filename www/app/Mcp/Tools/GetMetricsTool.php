<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ScopesToUser;
use App\Models\DailyMetric;
use App\Stores\Metric;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Günlük metrik zaman serisi döndürür. Gün/hafta/ay gruplama ve mağaza, uygulama veya ülke kırılımı destekler. Oran metrikleri (crash_rate, anr_rate, rating_avg) ortalama, diğerleri toplam alınır. Mağaza verisi 1-2 gün gecikmelidir.')]
class GetMetricsTool extends Tool
{
    use ScopesToUser;

    private const AVERAGED = [Metric::CRASH_RATE, Metric::ANR_RATE, Metric::RATING_AVG];

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            ...$this->appFilterRules(),
            'metric' => 'required|in:'.implode(',', Metric::all()),
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'granularity' => 'sometimes|in:day,week,month',
            'group_by' => 'sometimes|in:none,store,app,country',
            'country' => 'sometimes|string|size:2',
        ]);

        $period = $this->periodExpression($validated['granularity'] ?? 'day');
        $aggregate = in_array($validated['metric'], self::AVERAGED, true) ? 'AVG' : 'SUM';
        $groupBy = $validated['group_by'] ?? 'none';
        $country = strtoupper($validated['country'] ?? '');

        $groupColumn = match ($groupBy) {
            'store' => 'store_apps.store',
            'app' => 'store_apps.id',
            'country' => 'daily_metrics.country',
            default => null,
        };

        $rows = DailyMetric::query()
            ->join('store_apps', 'store_apps.id', '=', 'daily_metrics.store_app_id')
            ->whereIn('daily_metrics.store_app_id', $this->apps($request)->select('store_apps.id'))
            ->where('daily_metrics.metric', $validated['metric'])
            ->whereBetween('daily_metrics.date', [$validated['from'], $validated['to']])
            // Ülke kırılımı istenmedikçe yalnızca toplam satırları (ZZ) kullanılır; aksi halde çift sayım olur.
            ->when($groupBy === 'country',
                fn ($query) => $query->where('daily_metrics.country', '!=', DailyMetric::ALL_COUNTRIES),
                fn ($query) => $query->where('daily_metrics.country', $country ?: DailyMetric::ALL_COUNTRIES))
            ->selectRaw("{$period} as period")
            ->selectRaw("{$aggregate}(daily_metrics.value) as value")
            ->when($groupColumn, fn ($query) => $query->addSelect(DB::raw("{$groupColumn} as `group`"))->groupBy('group'))
            ->groupBy('period')
            ->orderBy('period')
            ->limit(5000)
            ->get()
            ->map(fn ($row) => array_filter([
                'period' => $row->period,
                'group' => $row->group ?? null,
                'value' => round((float) $row->value, 4),
            ], fn ($value) => $value !== null))
            ->all();

        return Response::structured([
            'metric' => $validated['metric'],
            'aggregate' => strtolower($aggregate),
            'rows' => $rows,
        ]);
    }

    private function periodExpression(string $granularity): string
    {
        $driver = DB::connection()->getDriverName();

        return match ([$granularity, $driver === 'sqlite']) {
            ['week', true] => "strftime('%Y-W%W', daily_metrics.date)",
            ['month', true] => "strftime('%Y-%m', daily_metrics.date)",
            ['week', false] => "DATE_FORMAT(daily_metrics.date, '%x-W%v')",
            ['month', false] => "DATE_FORMAT(daily_metrics.date, '%Y-%m')",
            default => 'daily_metrics.date',
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->appFilterSchema($schema),
            'metric' => $schema->string()->enum(Metric::all())->required()
                ->description('Normalize metrik adı.'),
            'from' => $schema->string()->required()->description('Başlangıç tarihi (YYYY-MM-DD).'),
            'to' => $schema->string()->required()->description('Bitiş tarihi (YYYY-MM-DD), dahil.'),
            'granularity' => $schema->string()->enum(['day', 'week', 'month'])->description('Varsayılan: day.'),
            'group_by' => $schema->string()->enum(['none', 'store', 'app', 'country'])
                ->description('Kırılım. store: mağazalar arası karşılaştırma için.'),
            'country' => $schema->string()->description('ISO 3166-1 alpha-2 ülke kodu (ör. TR).'),
        ];
    }
}
