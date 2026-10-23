<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'name'])]
class Product extends Model
{
    use BelongsToWorkspace;

    public function storeApps(): HasMany
    {
        return $this->hasMany(StoreApp::class);
    }
}
