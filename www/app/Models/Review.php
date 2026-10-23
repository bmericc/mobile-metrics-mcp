<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['store_app_id', 'store_review_id', 'rating', 'title', 'body', 'author', 'language', 'country', 'app_version', 'reviewed_at', 'reply_body', 'replied_at'])]
class Review extends Model
{
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'reviewed_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    public function storeApp(): BelongsTo
    {
        return $this->belongsTo(StoreApp::class);
    }
}
