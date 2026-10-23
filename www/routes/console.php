<?php

use App\Jobs\SyncStoreAccount;
use App\Models\StoreAccount;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mağaza raporları genelde gece yarısından sonra (UTC) güncelleniyor.
Schedule::call(function () {
    StoreAccount::query()->where('status', 'active')->each(fn (StoreAccount $account) => SyncStoreAccount::dispatch($account));
})->dailyAt('06:00')->name('sync-store-accounts')->withoutOverlapping();
