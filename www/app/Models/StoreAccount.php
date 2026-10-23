<?php

namespace App\Models;

use App\Enums\Store;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * credentials içeriği mağazaya göre değişir:
 *  - google_play: service_account_json, reports_bucket
 *  - app_store:   issuer_id, key_id, private_key (.p8 içeriği), vendor_number
 *  - app_gallery: client_id, client_secret
 */
#[Fillable(['workspace_id', 'store', 'label', 'credentials', 'status', 'last_verified_at', 'last_error'])]
#[Hidden(['credentials'])]
class StoreAccount extends Model
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return [
            'store' => Store::class,
            'credentials' => 'encrypted:array',
            'last_verified_at' => 'datetime',
        ];
    }

    public function storeApps(): HasMany
    {
        return $this->hasMany(StoreApp::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    public function rawReports(): HasMany
    {
        return $this->hasMany(RawReport::class);
    }
}
