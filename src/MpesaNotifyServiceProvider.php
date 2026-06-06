<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaNotify;

use FelixMuhoro\MpesaNotify\Channels\MpesaNotificationChannel;
use FelixMuhoro\MpesaNotify\Channels\SmsChannel;
use FelixMuhoro\MpesaNotify\Listeners\SendPaymentNotification;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class MpesaNotifyServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/mpesa-notify.php',
            'mpesa-notify'
        );

        $this->app->singleton(MpesaNotificationManager::class, function ($app) {
            return new MpesaNotificationManager($app['config']['mpesa-notify']);
        });

        $this->app->singleton(SmsChannel::class, function ($app) {
            return new SmsChannel(
                apiKey: $app['config']['mpesa-notify.africas_talking.api_key'],
                username: $app['config']['mpesa-notify.africas_talking.username'],
                senderId: $app['config']['mpesa-notify.africas_talking.sender_id'],
                sandbox: $app['config']['mpesa-notify.africas_talking.sandbox'] ?? false,
            );
        });

        // Register mpesa-sms notification channel
        $this->app->resolving(ChannelManager::class, function (ChannelManager $manager) {
            $manager->extend('mpesa-sms', function ($app) {
                return $app->make(SmsChannel::class);
            });

            $manager->extend('mpesa-notify', function ($app) {
                return $app->make(MpesaNotificationChannel::class);
            });
        });
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->registerPublishables();
        $this->registerEventListeners();
    }

    /**
     * Register publishable assets.
     */
    private function registerPublishables(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mpesa-notify.php' => config_path('mpesa-notify.php'),
            ], 'mpesa-notify-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'mpesa-notify-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/mpesa-notify'),
            ], 'mpesa-notify-views');
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mpesa-notify');
    }

    /**
     * Register event listeners for M-Pesa payment events.
     * Binds to events fired by felixmuhoro/laravel-mpesa core package.
     */
    private function registerEventListeners(): void
    {
        $coreEvents = [
            'FelixMuhoro\Mpesa\Events\PaymentSuccessful',
            'FelixMuhoro\Mpesa\Events\PaymentFailed',
            'FelixMuhoro\Mpesa\Events\StkPushInitiated',
        ];

        foreach ($coreEvents as $event) {
            if ($this->app['config']['mpesa-notify.listen_to_events'] ?? true) {
                Event::listen($event, SendPaymentNotification::class);
            }
        }
    }
}
