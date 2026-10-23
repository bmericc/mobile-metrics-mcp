<?php

namespace App\Filament\App\Resources\StoreAccounts;

use App\Models\Workspace;
use Illuminate\Support\Facades\Crypt;

/**
 * OAuth ile alınan, henüz bir geliştirici hesabına bağlanmamış Google Play bağlantısı.
 * Refresh token içerdiği için oturumda şifreli tutulur.
 */
class PendingPlayConnection
{
    private const KEY = 'store_connect.pending_google_play';

    /**
     * @param  array<string, mixed>  $credentials
     */
    public static function put(Workspace $workspace, array $credentials): void
    {
        session()->put(self::KEY, [
            'workspace_id' => $workspace->id,
            'credentials' => Crypt::encrypt($credentials),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(Workspace $workspace): ?array
    {
        $pending = session(self::KEY);

        if (($pending['workspace_id'] ?? null) !== $workspace->id) {
            return null;
        }

        return Crypt::decrypt($pending['credentials']);
    }

    public static function forget(): void
    {
        session()->forget(self::KEY);
    }
}
