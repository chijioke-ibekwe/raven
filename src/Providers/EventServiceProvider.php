<?php

namespace ChijiokeIbekwe\Raven\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use ChijiokeIbekwe\Raven\Events\Raven;
use ChijiokeIbekwe\Raven\Listeners\RavenListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Raven::class => [
            RavenListener::class,
        ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot(): void
    {
        parent::boot();
    }
}
