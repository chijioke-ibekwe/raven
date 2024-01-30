<?php

namespace ChijiokeIbekwe\Raven;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ChijiokeIbekwe\Raven\Channels\SendGridChannel;
use ChijiokeIbekwe\Raven\Console\InstallCommand;
use ChijiokeIbekwe\Raven\Providers\EventServiceProvider;
use SendGrid;

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
                    database_path('migrations/2023_05_12_142923_create_notification_contexts_table.php'),
                __DIR__ . '/../database/migrations/create_notification_channels_table.php.stub' =>
                    database_path('migrations/2023_05_12_142924_create_notification_channels_table.php'),
                __DIR__ . '/../database/migrations/create_notification_channel_notification_context_table.php.stub' =>
                    database_path('migrations/2023_05_12_142925_create_notification_channel_notification_context_table.php')
            ], 'raven-migrations');



            $this->commands([
                InstallCommand::class
            ]);
        }

        $this->registerRoutes();

        $this->app->singleton(SendGrid::class, function ($app) {
            return new SendGrid(config('raven.api-key.sendgrid'));
        });

        Notification::extend('sendgrid-mail', function ($app) {
            return new SendgridChannel();
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