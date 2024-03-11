<?php

namespace ChijiokeIbekwe\Raven\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Models\NotificationContext;

class SmsNotificationSender extends Notification implements ShouldQueue, INotificationSender
{
    use Queueable;

    public function __construct(public readonly Scroll              $scroll,
                                public readonly NotificationContext $notificationContext)
    {
        //
    }

    public function via($notifiable): array {
        return ['raven.default.sms'];
    }

    public function validateNotification()
    {
        // TODO: Implement validateNotification() method.
    }


}