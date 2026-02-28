<?php

namespace ChijiokeIbekwe\Raven\Providers;

use ChijiokeIbekwe\Raven\Events\Raven;
use ChijiokeIbekwe\Raven\Listeners\RavenListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Raven::class => [
            RavenListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }
}
