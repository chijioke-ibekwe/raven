<?php

namespace ChijiokeIbekwe\Raven\Notifications;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use SendGrid\Mail\Attachment;
use SendGrid\Mail\Bcc;
use SendGrid\Mail\Cc;
use SendGrid\Mail\Mail;
use SendGrid\Mail\TypeException;

class EmailNotificationSender extends Notification implements INotificationSender, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Scroll $scroll,
        public readonly NotificationContext $notificationContext)
    {
        $queue = config('raven.customizations.queue_name');
        if (! is_null($queue)) {
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
     * @throws RavenInvalidDataException|TypeException
     */
    public function toSendgrid(mixed $notifiable): ?Mail
    {

        $route = $notifiable instanceof AnonymousNotifiable ? $notifiable->routes[config('raven.default.email')] :
            $notifiable->routeNotificationFor('mail');

        if (! $route) {
            $class = get_class($notifiable);
            throw new RavenInvalidDataException(
                "Missing route for mail: ensure {$class}::routeNotificationForMail() is defined on the notifiable class"
            );
        }

        $email = new Mail;
        $email->setTemplateId($this->notificationContext->email_template_id);
        $email->addTo($route);

        if (! empty($ccs = $this->scroll->getCcs())) {
            if (! is_string(array_key_first($ccs))) {
                $cc_objects = [];
                foreach ($ccs as $cc) {
                    $cc_objects[] = new Cc($cc);
                }
                $email->addCcs($cc_objects);
            } else {
                $email->addCcs($ccs);
            }
        }

        if (! empty($bccs = $this->scroll->getBccs())) {
            if (! is_string(array_key_first($bccs))) {
                $bcc_objects = [];
                foreach ($bccs as $bcc) {
                    $bcc_objects[] = new Bcc($bcc);
                }
                $email->addBccs($bcc_objects);
            } else {
                $email->addBccs($bccs);
            }
        }

        if ($replyTo = $this->scroll->getReplyTo()) {
            $email->setReplyTo($replyTo);
        }

        $substitutions = $this->scroll->getParams();
        $email->addDynamicTemplateDatas($substitutions);

        if (! empty($this->scroll->getAttachmentUrls())) {
            $attachments = [];
            foreach ($this->scroll->getAttachmentUrls() as $url) {
                $attachment = new Attachment;
                $filename = basename($url);
                $binary_content = file_get_contents($url);
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($binary_content) ?: 'application/octet-stream';
                $attachment->setContent(base64_encode($binary_content));
                $attachment->setType($mimeType);
                $attachment->setFilename($filename);
                $attachment->setDisposition('attachment');

                $attachments[] = $attachment;
            }
            $email->addAttachments($attachments);
        }

        return $email;
    }

    /**
     * Get the PHPMailer object for Amazon SES channel.
     *
     * @throws RavenInvalidDataException|TypeException|Exception
     */
    public function toAmazonSes(mixed $notifiable): ?PHPMailer
    {

        $route = $notifiable instanceof AnonymousNotifiable ? $notifiable->routes[config('raven.default.email')] :
            $notifiable->routeNotificationFor('mail');

        if (! $route) {
            $class = get_class($notifiable);
            throw new RavenInvalidDataException(
                "Missing route for mail: ensure {$class}::routeNotificationForMail() is defined on the notifiable class"
            );
        }

        $email = new PHPMailer(true);
        $email->addAddress($route);

        if (! empty($this->scroll->getCcs())) {
            foreach ($this->scroll->getCcs() as $key => $value) {
                ! is_string($key) ? $email->addCc($value) : $email->addCc($key, $value);
            }
        }

        if (! empty($this->scroll->getBccs())) {
            foreach ($this->scroll->getBccs() as $key => $value) {
                ! is_string($key) ? $email->addBCC($value) : $email->addBCC($key, $value);
            }
        }

        if ($replyTo = $this->scroll->getReplyTo()) {
            $email->addReplyTo($replyTo);
        }

        if (! empty($this->scroll->getAttachmentUrls())) {
            foreach ($this->scroll->getAttachmentUrls() as $url) {
                $filename = basename($url);
                $binary_content = file_get_contents($url);

                if ($binary_content === false) {
                    throw new Exception("Could not fetch remote content from: '$url'");
                }

                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($binary_content) ?: 'application/octet-stream';
                $email->AddStringAttachment($binary_content, $filename, PHPMailer::ENCODING_BASE64, $mimeType);
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

        if (config('raven.default.email') == 'sendgrid') {
            throw_if(empty($this->notificationContext->email_template_id), RavenInvalidDataException::class,
                "Email notification context with name $context_name has no email template id");
        }

        if (config('raven.default.email') == 'ses') {
            throw_if(empty($this->notificationContext->email_template_id) && config('raven.providers.ses.template_source') == 'sendgrid', RavenInvalidDataException::class,
                "Email notification context with name $context_name has no email template id");

            throw_if(empty($this->notificationContext->email_template_filename) && config('raven.providers.ses.template_source') == 'filesystem', RavenInvalidDataException::class,
                "Email notification context with name $context_name has no email template file name");
        }
    }
}
