<?php

namespace ChijiokeIbekwe\Raven\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use ChijiokeIbekwe\Raven\Data\NotificationData;

class Raven
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public NotificationData $notificationData)
    {
        //
    }
}