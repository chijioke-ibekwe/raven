<?php

namespace ChijiokeIbekwe\Raven\Notifications;

use ChijiokeIbekwe\Raven\Library\TemplateCleaner;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Models\NotificationContext;

class DatabaseNotificationSender extends Notification implements ShouldQueue, INotificationSender
{
    use Queueable;

    const IN_APP_FOLDER = '/in_app/';

    public function __construct(public readonly Scroll              $scroll,
                                public readonly NotificationContext $notificationContext)
    {
        $queue = config('raven.customizations.queue_name');
        if(!is_null($queue)) {
            $this->queue = $queue;
        }
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
        $template_location = config('raven.customizations.templates_directory') . self::IN_APP_FOLDER .
            $this->notificationContext->in_app_template_filename;

        $cleaned_template = TemplateCleaner::cleanFile($this->scroll->getParams(), $template_location);

        return json_decode($cleaned_template, true);
    }

    /**
     * @throws \Throwable
     */
    public function validateNotification(): void
    {
        $context_name = $this->notificationContext->name;
        $in_app_template_directory = config('raven.customizations.templates_directory') . self::IN_APP_FOLDER;

        throw_if(empty($this->notificationContext->in_app_template_filename), RavenInvalidDataException::class,
            "Database notification context with name $context_name has no template filename");

        throw_if(!file_exists($in_app_template_directory . $this->notificationContext->in_app_template_filename), RavenInvalidDataException::class,
            "Database notification context with name $context_name has no template file in $in_app_template_directory");
    }

}