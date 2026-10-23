<?php

use App\Mcp\Servers\StoreMetricsServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp', StoreMetricsServer::class)
    ->middleware('auth:api');
