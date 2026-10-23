<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['store_account_id', 'store_app_id', 'source', 'period_start', 'period_end', 'path', 'checksum', 'processed_at', 'error'])]
class RawReport extends Model
{
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'processed_at' => 'datetime',
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
