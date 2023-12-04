<?php

namespace ChijiokeIbekwe\Messenger\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use ChijiokeIbekwe\Messenger\Data\NotificationData;
use ChijiokeIbekwe\Messenger\Models\NotificationContext;

class SmsNotificationSender extends Notification implements ShouldQueue, INotificationSender
{
    use Queueable;

    public function __construct(public readonly NotificationData    $notificationDTO,
                                public readonly NotificationContext $notificationContext)
    {
        //
    }

    public function via($notifiable): array {
        return ['messenger.notification-service.sms'];
    }

    public function validateNotification()
    {
        // TODO: Implement validateNotification() method.
    }


}