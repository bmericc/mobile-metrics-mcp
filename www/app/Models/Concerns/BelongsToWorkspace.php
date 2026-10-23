<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Global scope bilinçli olarak yok: job'lar tüm workspace'ler üzerinde çalışır.
 * Kullanıcıya veri döndüren her yer (MCP araçları) visibleTo() ile filtreler;
 * Filament paneli ise tenancy üzerinden otomatik filtreler.
 */
trait BelongsToWorkspace
{
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn($this->qualifyColumn('workspace_id'), $user->workspaceIds());
    }
}
