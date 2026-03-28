<?php

namespace ChijiokeIbekwe\Raven;

use Aws\Ses\SesClient;
use ChijiokeIbekwe\Raven\Channels\AmazonSesChannel;
use ChijiokeIbekwe\Raven\Channels\SendGridChannel;
use ChijiokeIbekwe\Raven\Channels\TwilioChannel;
use ChijiokeIbekwe\Raven\Channels\VonageChannel;
use ChijiokeIbekwe\Raven\Console\InstallCommand;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use SendGrid;
use Vonage\Client as VonageClient;
use Twilio\Rest\Client as TwilioClient;
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
        $providers = array_unique([
            config('raven.default.email'),
            config('raven.default.sms'),
            config('raven.providers.ses.template_source'),
        ]);

        foreach ($providers as $provider) {

            switch ($provider) {
                case 'sendgrid':
                    $this->app->singleton(SendGrid::class, fn ($app) => new SendGrid(config('raven.providers.sendgrid.key')));
                    Notification::extend('sendgrid', fn ($app) => new SendGridChannel);
                    break;

                case 'ses':
                    $this->app->singleton(SesClient::class, fn ($app) => new SesClient([
                        'credentials' => Arr::only(config('raven.providers.ses'), ['key', 'secret']),
                        'version' => 'latest',
                        'region' => config('raven.providers.ses.region'),
                    ]));
                    Notification::extend('ses', fn ($app) => new AmazonSesChannel);
                    break;

                case 'vonage':
                    $this->app->singleton(VonageClient::class, fn ($app) => new VonageClient(new Basic(
                        config('raven.providers.vonage.api_key'),
                        config('raven.providers.vonage.api_secret')
                    )));
                    Notification::extend('vonage', fn ($app) => new VonageChannel);
                    break;

                case 'twilio':
                    $this->app->singleton(TwilioClient::class, fn ($app) => new TwilioClient(
                        config('raven.providers.twilio.account_sid'),
                        config('raven.providers.twilio.auth_token')
                    ));
                    Notification::extend('twilio', fn ($app) => new TwilioChannel());
                    break;
            }
        }
    }
}
