<?php

namespace ChijiokeIbekwe\Raven;

use Aws\Ses\SesClient;
use ChijiokeIbekwe\Raven\Channels\AmazonSesChannel;
use ChijiokeIbekwe\Raven\Channels\VonageChannel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ChijiokeIbekwe\Raven\Channels\SendGridChannel;
use ChijiokeIbekwe\Raven\Console\InstallCommand;
use ChijiokeIbekwe\Raven\Providers\EventServiceProvider;
use SendGrid;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;

class RavenServiceProvider extends ServiceProvider {

    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {


            //publish config file
            $this->publishes([
                __DIR__.'/../config/raven.php' => config_path('raven.php'),
            ], 'raven-config');


            //publish migration files
            $this->publishes([
                __DIR__ . '/../database/migrations/create_notification_contexts_table.php.stub' =>
                    database_path('migrations/2023_05_12_142923_create_notification_contexts_table.php')
            ], 'raven-migrations');



            $this->commands([
                InstallCommand::class
            ]);
        }

        $this->registerRoutes();

        $this->app->singleton(SendGrid::class, function ($app) {
            return new SendGrid(config('raven.providers.sendgrid.key'));
        });

        $this->app->singleton(SesClient::class, function ($app) {
            return new SesClient([
                'credentials' => Arr::only(config('raven.providers.ses'), ['key', 'secret']),
                'version' => 'latest',
                'region' => config('raven.providers.ses.region')
            ]);
        });

        $this->app->singleton(Client::class, function ($app) {
            return new Client(new Basic(config('raven.providers.vonage.api_key'), config('raven.providers.vonage.api_secret')));
        });

        Notification::extend('sendgrid', function ($app) {
            return new SendGridChannel();
        });

        Notification::extend('ses', function ($app) {
            return new AmazonSesChannel();
        });

        Notification::extend('vonage', function ($app) {
            return new VonageChannel();
        });
    }

    protected function registerRoutes(): void
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });
    }

    protected function routeConfiguration(): array
    {
        return [
            'prefix' => config('raven.api.prefix'),
            'middleware' => config('raven.api.middleware'),
        ];
    }
}