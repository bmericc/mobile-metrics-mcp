<?php

namespace App\Providers;

use App\Auth\HuaweiProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use SocialiteProviders\Apple\AppleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(function (SocialiteWasCalled $event) {
            (new AppleExtendSocialite)->handle($event);
            $event->extendSocialite('huawei', HuaweiProvider::class);
        });

        Passport::authorizationView(fn ($parameters) => view('mcp.authorize', $parameters));
    }
}
