<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ScopesToUser;
use App\Models\DailyMetric;
use App\Stores\Metric;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Bir metriği iki dönem arasında karşılaştırır (ör. bu ay / geçen ay) ve mutlak ile yüzde değişimi döndürür.')]
class ComparePeriodsTool extends Tool
{
    use ScopesToUser;

    private const AVERAGED = [Metric::CRASH_RATE, Metric::ANR_RATE, Metric::RATING_AVG];

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            ...$this->appFilterRules(),
            'metric' => 'required|in:'.implode(',', Metric::all()),
            'current_from' => 'required|date',
            'current_to' => 'required|date|after_or_equal:current_from',
            'previous_from' => 'required|date',
            'previous_to' => 'required|date|after_or_equal:previous_from',
        ]);

        $current = $this->total($request, $validated['metric'], $validated['current_from'], $validated['current_to']);
        $previous = $this->total($request, $validated['metric'], $validated['previous_from'], $validated['previous_to']);

        return Response::structured([
            'metric' => $validated['metric'],
            'current' => $current,
            'previous' => $previous,
            'change' => round($current - $previous, 4),
            'change_pct' => $previous != 0.0 ? round(($current - $previous) / abs($previous) * 100, 2) : null,
        ]);
    }

    private function total(Request $request, string $metric, string $from, string $to): float
    {
        $query = DailyMetric::query()
            ->whereIn('store_app_id', $this->apps($request)->select('id'))
            ->where('metric', $metric)
            ->where('country', DailyMetric::ALL_COUNTRIES)
            ->whereBetween('date', [$from, $to]);

        return round((float) (in_array($metric, self::AVERAGED, true) ? $query->avg('value') : $query->sum('value')), 4);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->appFilterSchema($schema),
            'metric' => $schema->string()->enum(Metric::all())->required(),
            'current_from' => $schema->string()->required()->description('YYYY-MM-DD'),
            'current_to' => $schema->string()->required()->description('YYYY-MM-DD'),
            'previous_from' => $schema->string()->required()->description('YYYY-MM-DD'),
            'previous_to' => $schema->string()->required()->description('YYYY-MM-DD'),
        ];
    }
}
