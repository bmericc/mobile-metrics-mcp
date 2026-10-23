<?php

namespace App\Models;

use App\Enums\Store;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * external_id: Google Play package name, App Store app id (sayısal), AppGallery appId.
 */
#[Fillable(['workspace_id', 'store_account_id', 'product_id', 'store', 'external_id', 'name', 'icon_url', 'is_active'])]
class StoreApp extends Model
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return [
            'store' => Store::class,
            'is_active' => 'boolean',
        ];
    }

    public function storeAccount(): BelongsTo
    {
        return $this->belongsTo(StoreAccount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(DailyMetric::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
