<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Normalize metrik adları için bkz. App\Stores\Metric.
 */
#[Fillable(['store_app_id', 'date', 'country', 'metric', 'value'])]
class DailyMetric extends Model
{
    public const ALL_COUNTRIES = 'ZZ';

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
        ];
    }

    /**
     * 'date' cast'i saat ekleyerek (Y-m-d H:i:s) yazar; tarih karşılaştırmaları ve unique
     * index her veritabanında tutarlı kalsın diye yalnızca Y-m-d saklanır.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? CarbonImmutable::parse($value)->startOfDay() : null,
            set: fn (mixed $value) => CarbonImmutable::parse($value)->toDateString(),
        );
    }

    public function storeApp(): BelongsTo
    {
        return $this->belongsTo(StoreApp::class);
    }
}
