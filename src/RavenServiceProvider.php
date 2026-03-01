<?php

namespace ChijiokeIbekwe\Raven;

use Aws\Ses\SesClient;
use ChijiokeIbekwe\Raven\Channels\AmazonSesChannel;
use ChijiokeIbekwe\Raven\Channels\SendGridChannel;
use ChijiokeIbekwe\Raven\Channels\VonageChannel;
use ChijiokeIbekwe\Raven\Console\InstallCommand;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use SendGrid;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;

class RavenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/raven.php', 'raven');
        $this->mergeConfigFrom(__DIR__.'/../config/notification-contexts.php', 'notification-contexts');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {

            // publish config file
            $this->publishes([
                __DIR__.'/../config/raven.php' => config_path('raven.php'),
            ], 'raven-config');

            // publish notification contexts config file
            $this->publishes([
                __DIR__.'/../config/notification-contexts.php' => config_path('notification-contexts.php'),
            ], 'raven-contexts');

            $this->commands([
                InstallCommand::class,
            ]);
        }

        $this->registerProviders();
    }

    protected function registerProviders(): void
    {
        $emailProvider = config('raven.default.email');
        $smsProvider = config('raven.default.sms');

        $needsSendGrid = $emailProvider === 'sendgrid' ||
            ($emailProvider === 'ses' && config('raven.providers.ses.template_source') === 'sendgrid');

        if ($needsSendGrid) {
            $this->app->singleton(SendGrid::class, function ($app) {
                return new SendGrid(config('raven.providers.sendgrid.key'));
            });

            Notification::extend('sendgrid', function ($app) {
                return new SendGridChannel;
            });
        }

        if ($emailProvider === 'ses') {
            $this->app->singleton(SesClient::class, function ($app) {
                return new SesClient([
                    'credentials' => Arr::only(config('raven.providers.ses'), ['key', 'secret']),
                    'version' => 'latest',
                    'region' => config('raven.providers.ses.region'),
                ]);
            });

            Notification::extend('ses', function ($app) {
                return new AmazonSesChannel;
            });
        }

        if ($smsProvider === 'vonage') {
            $this->app->singleton(Client::class, function ($app) {
                return new Client(new Basic(
                    config('raven.providers.vonage.api_key'),
                    config('raven.providers.vonage.api_secret')
                ));
            });

            Notification::extend('vonage', function ($app) {
                return new VonageChannel;
            });
        }
    }
}
