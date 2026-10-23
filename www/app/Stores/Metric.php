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

    public const IMPRESSIONS = 'impressions';

    public const PAGE_VIEWS = 'page_views';

    public const SESSIONS = 'sessions';

    public const REVENUE = 'revenue';

    public const CRASHES = 'crashes';

    /** 0-1 arası kesir (0.0123 = %1,23). */
    public const CRASH_RATE = 'crash_rate';

    public const ANR_RATE = 'anr_rate';

    public const RATING_AVG = 'rating_avg';

    public const RATING_COUNT = 'rating_count';

    /**
     * Toplanabilir (gün/ülke/mağaza üzerinden toplanan) metrikler ve Türkçe adları.
     *
     * @return array<string, string>
     */
    public static function countLabels(): array
    {
        return [
            self::INSTALLS => 'Kurulum / indirme',
            self::UNINSTALLS => 'Kaldırma',
            self::UPDATES => 'Güncelleme',
            self::IMPRESSIONS => 'Gösterim',
            self::PAGE_VIEWS => 'Sayfa görüntüleme',
            self::ACTIVE_DEVICES => 'Aktif cihaz',
            self::SESSIONS => 'Oturum',
            self::CRASHES => 'Çökme',
        ];
    }

    /**
     * @return array<string>
     */
    public static function all(): array
    {
        return array_values(array_filter((new \ReflectionClass(self::class))->getConstants(), 'is_string'));
    }
}
