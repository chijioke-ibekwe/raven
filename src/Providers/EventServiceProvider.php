<?php

namespace ChijiokeIbekwe\Messenger\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use ChijiokeIbekwe\Messenger\Events\MessengerEvent;
use ChijiokeIbekwe\Messenger\Listeners\MessengerListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MessengerEvent::class => [
            MessengerListener::class,
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
