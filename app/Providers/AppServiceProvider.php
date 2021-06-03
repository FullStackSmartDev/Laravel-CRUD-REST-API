<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Twilio\Jwt\ClientToken;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
//        $this->app->bind(
//            ClientToken::class,
//            function ($app) {
//                $accountSid = env('ACCOUNT_SID');
//                $authToken = env('AUTH_TOKEN');
//                $apiKey = env('API_KEY');
//                $apiSecret = env('API_SECRET');
//
//                return new ClientToken($accountSid, $authToken);
//            }
//        );

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
