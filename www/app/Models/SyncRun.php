<?php

namespace App\Models;

use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['store_account_id', 'store_app_id', 'job', 'status', 'rows', 'error', 'started_at', 'finished_at'])]
class SyncRun extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SyncStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function storeAccount(): BelongsTo
    {
        return $this->belongsTo(StoreAccount::class);
    }

    public function storeApp(): BelongsTo
    {
        return $this->belongsTo(StoreApp::class);
    }
}
