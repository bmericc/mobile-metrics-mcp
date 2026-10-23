<?php

namespace App\Actions;

use App\Models\StoreAccount;
use App\Stores\StoreRegistry;
use Throwable;

/**
 * Kimlik bilgilerini mağazaya karşı dener; sonucu hesabın status alanına yazar.
 * Scheduler yalnızca status = active hesapları senkronize eder.
 */
class VerifyStoreAccount
{
    public function __construct(private readonly StoreRegistry $registry) {}

    public function __invoke(StoreAccount $account): bool
    {
        try {
            $this->registry->for($account->store)->verify($account);
        } catch (Throwable $e) {
            $account->update(['status' => 'failed', 'last_error' => $e->getMessage()]);

            return false;
        }

        $account->update(['status' => 'active', 'last_verified_at' => now(), 'last_error' => null]);

        return true;
    }
}
