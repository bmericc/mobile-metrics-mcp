<?php

namespace App\Mcp\Servers;

use App\AppVersion;
use App\Mcp\Tools\ComparePeriodsTool;
use App\Mcp\Tools\GetMetricsTool;
use App\Mcp\Tools\GetReviewsTool;
use App\Mcp\Tools\ListAppsTool;
use App\Mcp\Tools\SyncStatusTool;
use App\Mcp\Tools\TopCountriesTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Mobile Metrics MCP')]
#[Version(AppVersion::CURRENT)]
#[Instructions('Google Play, Apple App Store ve Huawei AppGallery\'de yayınlanan uygulamaların istatistiklerini (kurulum, kaldırma, aktif cihaz, gelir, crash/ANR, puan) ve kullanıcı yorumlarını sorgular. Yalnızca token sahibinin workspace\'lerindeki geliştirici hesapları görünür. Önce list-apps ile store_app/product id\'lerini bul; aynı uygulamanın mağazalardaki karşılıkları product altında gruplanır. Metrikler günlüktür ve mağazaya göre 1-2 gün gecikmeli gelir; güncelliği sync-status ile kontrol et. Araçlar ham veri döndürür, yorumu sen yap. Salt okunurdur; yorumlara yanıt verme gibi yazma işlemleri yalnızca panelden yapılır.')]
class StoreMetricsServer extends Server
{
    protected array $tools = [
        ListAppsTool::class,
        GetMetricsTool::class,
        ComparePeriodsTool::class,
        TopCountriesTool::class,
        GetReviewsTool::class,
        SyncStatusTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
