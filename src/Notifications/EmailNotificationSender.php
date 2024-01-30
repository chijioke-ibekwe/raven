<?php

namespace ChijiokeIbekwe\Raven\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use ChijiokeIbekwe\Raven\Data\NotificationData;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Models\NotificationContext;
use SendGrid\Mail\Attachment;
use SendGrid\Mail\Mail;
use SendGrid\Mail\TypeException;

class EmailNotificationSender extends Notification implements ShouldQueue, INotificationSender
{
    use Queueable;

    public function __construct(public readonly NotificationData    $notificationData,
                                public readonly NotificationContext $notificationContext)
    {
        //
    }

    public function via(mixed $notifiable): array
    {
        return [config('raven.notification-service.email')];
    }

    /**
     * Get the Sendgrid representation of the notification.
     *
     * @param mixed $notifiable
     * @return Mail|null
     * @throws RavenInvalidDataException|TypeException
     */
    public function toSendgrid(mixed $notifiable): ?Mail {

        $provider = config('raven.notification-service.email');

        $route = $notifiable->routeNotificationFor('mail');

        if (!$route) {
            throw new RavenInvalidDataException("Missing route for $provider");
        }

        $email = new Mail();
        $email->setTemplateId($this->notificationContext->email_template_id);
        $email->addTo($route);

        if(!empty($this->notificationData->getCcs())){
            $email->addCcs($this->notificationData->getCcs());
        }

        $substitutions = $this->notificationData->getParams();
        $email->addDynamicTemplateDatas($substitutions);

        if(!empty($this->notificationData->getAttachmentUrls())) {
            $attachments = [];
            foreach ($this->notificationData->getAttachmentUrls() as $url){
                $attachment = new Attachment();
                $filename = basename($url);
                $file_encoded = base64_encode(file_get_contents($url));
                $attachment->setContent($file_encoded);
                $attachment->setType("application/text");
                $attachment->setFilename($filename);
                $attachment->setDisposition("attachment");

                $attachments[] = $attachment;
            }
            $email->addAttachments($attachments);
        }

        return $email;
    }

    /**
     * @throws \Throwable
     */
    public function validateNotification(): void
    {
        $context_name = $this->notificationContext->name;

        throw_if(empty($this->notificationContext->email_template_id), RavenInvalidDataException::class,
            "Email notification context with name $context_name has no email template id");
    }
}