<?php

namespace App\Stores\AppStore;

use RuntimeException;

/**
 * Uygulamanın Analytics Reports isteği yok ve anahtar Admin olmadığı için açılamıyor.
 */
class AnalyticsUnavailable extends RuntimeException {}
