<?php

namespace ChijiokeIbekwe\Raven\Notifications;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Models\NotificationContext;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use SendGrid\Mail\Attachment;
use SendGrid\Mail\Cc;
use SendGrid\Mail\Mail;
use SendGrid\Mail\TypeException;

class EmailNotificationSender extends Notification implements ShouldQueue, INotificationSender
{
    use Queueable;

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
        return [config('raven.default.email')];
    }

    /**
     * Get the Sendgrid representation of the notification.
     *
     * @param mixed $notifiable
     * @return Mail|null
     * @throws RavenInvalidDataException|TypeException
     */
    public function toSendgrid(mixed $notifiable): ?Mail {

        $route = $notifiable instanceof AnonymousNotifiable ? $notifiable->routes[config('raven.default.email')] :
            $notifiable->routeNotificationFor('mail');

        if (!$route) {
            throw new RavenInvalidDataException("Missing route for mail");
        }

        $email = new Mail();
        $email->setTemplateId($this->notificationContext->email_template_id);
        $email->addTo($route);

        if(!empty($ccs = $this->scroll->getCcs())){
            if(gettype(array_key_first($ccs)) !== 'string') {
                $cc_objects = [];
                foreach ($ccs as $cc) {
                    $cc_objects[] = new Cc($cc);
                }
                $email->addCcs($cc_objects);
            } else {
                $email->addCcs($ccs);
            }
        }

        $substitutions = $this->scroll->getParams();
        $email->addDynamicTemplateDatas($substitutions);

        if(!empty($this->scroll->getAttachmentUrls())) {
            $attachments = [];
            foreach ($this->scroll->getAttachmentUrls() as $url){
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
     * Get the PHPMailer object for Amazon SES channel.
     *
     * @param mixed $notifiable
     * @return PHPMailer|null
     * @throws RavenInvalidDataException|TypeException|Exception
     */
    public function toAmazonSes(mixed $notifiable): ?PHPMailer {

        $route = $notifiable instanceof AnonymousNotifiable ? $notifiable->routes[config('raven.default.email')] :
            $notifiable->routeNotificationFor('mail');

        if (!$route) {
            throw new RavenInvalidDataException("Missing route for mail");
        }

        $email = new PHPMailer(true);
        $email->addAddress($route);

        if(!empty($this->scroll->getCcs())){
            foreach ($this->scroll->getCcs() as $key => $value){
                gettype($key) !== 'string' ? $email->addCc($value) : $email->addCc($key, $value);
            }
        }

        if(!empty($this->scroll->getAttachmentUrls())) {
            foreach ($this->scroll->getAttachmentUrls() as $url){
                $filename = basename($url);
                $binary_content = file_get_contents($url);

                if ($binary_content === false) {
                    throw new Exception("Could not fetch remote content from: '$url'");
                }

                $email->AddStringAttachment($binary_content, $filename);
            }
        }

        return $email;
    }

    /**
     * @throws \Throwable
     */
    public function validateNotification(): void
    {
        $context_name = $this->notificationContext->name;

        if(config('raven.default.email') == 'sendgrid') {
            throw_if(empty($this->notificationContext->email_template_id), RavenInvalidDataException::class,
            "Email notification context with name $context_name has no email template id");
        }

        if(config('raven.default.email') == 'ses') {
            throw_if(empty($this->notificationContext->email_template_id) && config('raven.providers.ses.template_source') == 'sendgrid', RavenInvalidDataException::class,
            "Email notification context with name $context_name has no email template id");

            throw_if(empty($this->notificationContext->email_template_filename) && config('raven.providers.ses.template_source') == 'filesystem', RavenInvalidDataException::class,
            "Email notification context with name $context_name has no email template file name");
        }
    }
}