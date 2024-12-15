<?php

namespace ChijiokeIbekwe\Raven\Notifications;

use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Library\TemplateCleaner;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Models\NotificationContext;
use Vonage\SMS\Message\SMS;

class SmsNotificationSender extends Notification implements ShouldQueue, INotificationSender
{
    use Queueable;

    const SMS_FOLDER = '/sms/';

    public function __construct(public readonly Scroll              $scroll,
                                public readonly NotificationContext $notificationContext)
    {
        $queue = config('raven.customizations.queue_name');
        if(!is_null($queue)) {
            $this->queue = $queue;
        }
    }

    public function via(mixed $notifiable): array {
        return [config('raven.default.sms')];
    }

    /**
     * Get the Vonage SMS object.
     *
     * @param mixed $notifiable
     * @return SMS|null
     * @throws RavenInvalidDataException
     */
    public function toVonage(mixed $notifiable): ?SMS {

        $route = $notifiable instanceof AnonymousNotifiable ? $notifiable->routes[config('raven.default.sms')] :
            $notifiable->routeNotificationFor('vonage');

        if (!$route) {
            throw new RavenInvalidDataException("Missing route for vonage");
        }

        $template_location = config('raven.customizations.templates_directory') . self::SMS_FOLDER .
            $this->notificationContext->sms_template_filename;

        $cleaned_template = TemplateCleaner::cleanFile($this->scroll->getParams(), $template_location);

        return new SMS($route, config('raven.customizations.sms.from.name'), $cleaned_template);
    }

    /**
     * @throws \Throwable
     */
    public function validateNotification(): void
    {
        $context_name = $this->notificationContext->name;
        $sms_template_directory = config('raven.customizations.templates_directory') . self::SMS_FOLDER;

        throw_if(empty($this->notificationContext->sms_template_filename), RavenInvalidDataException::class,
            "SMS notification context with name $context_name has no template filename");

        throw_if(!file_exists($sms_template_directory . $this->notificationContext->sms_template_filename), RavenInvalidDataException::class,
            "SMS notification context with name $context_name has no template file in $sms_template_directory");
    }
}