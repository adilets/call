<?php

namespace App\Providers;

use App\Contracts\SmsSenderInterface;
use App\Contracts\EmailSenderInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsSenderInterface::class, function ($app) {
            $defaultSenderKey = config('sms.default');
            $senderConfig = config("sms.senders.{$defaultSenderKey}");

            $driverClass = $senderConfig['driver'];

            return new $driverClass();
        });

        $this->app->bind(EmailSenderInterface::class, function ($app) {
            $defaultSenderKey = config('email.default');
            $senderConfig = config("email.senders.{$defaultSenderKey}");

            $driverClass = $senderConfig['driver'];

            return new $driverClass();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
