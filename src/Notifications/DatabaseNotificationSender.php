<?php

namespace ChijiokeIbekwe\Raven\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use ChijiokeIbekwe\Raven\Data\NotificationData;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Models\NotificationContext;

class DatabaseNotificationSender extends Notification implements ShouldQueue, INotificationSender
{
    use Queueable;

    public function __construct(public readonly NotificationData    $notificationData,
                                public readonly NotificationContext $notificationContext)
    {
        //
    }

    public function via($notifiable): array
    {
        return [config('raven.notification-service.database')];
    }

    /**
     * Get the database representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toDatabase(mixed $notifiable): array {
        $param_keys = array_keys($this->notificationData->getParams());

        for ($i = 0; $i < count($param_keys); $i++) {
            $old_key = $param_keys[$i];
            $param_keys[$i] = '{' . $old_key . '}';
        }

        $param_values = array_values($this->notificationData->getParams());

        $body = str_replace($param_keys, $param_values, $this->notificationContext->body);
        $id = data_get($this->notificationData->getParams(), 'id') ?? null;

        return [
            'title' => $this->notificationContext->title,
            'body' => $body,
            'type' => $this->notificationContext->type,
            'id' => $id
        ];
    }

    /**
     * @throws \Throwable
     */
    public function validateNotification(): void
    {
        $context_name = $this->notificationContext->name;

        throw_if(empty($this->notificationContext->title), RavenInvalidDataException::class,
            "Database notification context with name $context_name has no title");

        throw_if(empty($this->notificationContext->body), RavenInvalidDataException::class,
            "Database notification context with name $context_name has no body");
    }

}