<?php

namespace App\Stores;

/**
 * Mağazalar arası normalize metrik adları. Collector'lar mağazanın kendi
 * kolon adlarını bunlara çevirir; MCP araçları yalnızca bu adları görür.
 */
final class Metric
{
    public const INSTALLS = 'installs';

    public const UNINSTALLS = 'uninstalls';

    public const UPDATES = 'updates';

    public const ACTIVE_DEVICES = 'active_devices';

    public const PAGE_VIEWS = 'page_views';

    public const SESSIONS = 'sessions';

    public const REVENUE = 'revenue';

    public const CRASH_RATE = 'crash_rate';

    public const ANR_RATE = 'anr_rate';

    public const RATING_AVG = 'rating_avg';

    public const RATING_COUNT = 'rating_count';

    /**
     * @return array<string>
     */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
