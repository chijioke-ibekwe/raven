<?php

namespace ChijiokeIbekwe\Raven\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Models\NotificationContext;

class DatabaseNotificationSender extends Notification implements ShouldQueue, INotificationSender
{
    use Queueable;

    public function __construct(public readonly Scroll              $scroll,
                                public readonly NotificationContext $notificationContext)
    {
        //
    }

    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the notification's database type.
     *
     * @return string
     */
    public function databaseType(object $notifiable): string
    {
        return $this->notificationContext->name;
    }

    /**
     * Get the database representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toDatabase(object $notifiable): array 
    {
        $param_keys = array_keys($this->scroll->getParams());

        for ($i = 0; $i < count($param_keys); $i++) {
            $old_key = $param_keys[$i];
            $param_keys[$i] = '{' . $old_key . '}';
        }

        $param_values = array_values($this->scroll->getParams());

        $body = str_replace($param_keys, $param_values, $this->notificationContext->body);

        return [
            'title' => $this->notificationContext->title,
            'body' => $body
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