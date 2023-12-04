<?php

namespace ChijiokeIbekwe\Messenger\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use ChijiokeIbekwe\Messenger\Data\NotificationData;

class MessengerEvent
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