<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'owner_id'])]
class Workspace extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Workspace $workspace) {
            $workspace->slug ??= Str::slug($workspace->name).'-'.Str::lower(Str::random(6));
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')->withPivot('role')->withTimestamps();
    }

    public function storeAccounts(): HasMany
    {
        return $this->hasMany(StoreAccount::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function storeApps(): HasMany
    {
        return $this->hasMany(StoreApp::class);
    }
}
