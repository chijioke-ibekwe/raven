<?php

namespace ChijiokeIbekwe\Raven\Channels;

use ChijiokeIbekwe\Raven\Exceptions\RavenDeliveryException;
use ChijiokeIbekwe\Raven\Notifications\EmailNotification;
use ChijiokeIbekwe\Raven\Templates\TemplateStrategy;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Mailgun\Mailgun;
use Throwable;

class MailgunChannel
{
    private Mailgun $mailgun;

    private TemplateStrategy $templateStrategy;

    public function __construct()
    {
        $this->mailgun = app(Mailgun::class);
        $this->templateStrategy = app(TemplateStrategy::class);
    }

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $emailNotification): void
    {
        if (! $emailNotification instanceof EmailNotification) {
            throw new RavenDeliveryException('MailgunChannel requires an EmailNotification notification');
        }

        if ($emailNotification->notificationContext->email_template_id) {
            $this->sendWithStoredTemplate($notifiable, $emailNotification);
        } else {
            $this->sendWithFilesystemTemplate($notifiable, $emailNotification);
        }
    }

    /**
     * @throws RavenDeliveryException
     */
    private function sendWithStoredTemplate(mixed $notifiable, EmailNotification $emailNotification): void
    {
        $payload = $emailNotification->toMailgun($notifiable);
        $sender = config('raven.customizations.email.from');
        $context = $emailNotification->notificationContext;

        $params = array_filter([
            'from' => "{$sender['name']} <{$sender['address']}>",
            'to' => $payload['to'],
            'cc' => $payload['cc'],
            'bcc' => $payload['bcc'],
            'h:Reply-To' => $payload['replyTo'],
            'template' => $context->email_template_id,
            'h:X-Mailgun-Variables' => json_encode($emailNotification->scroll->getParams()),
        ], fn ($v) => $v !== null);

        if (! empty($payload['attachments'])) {
            $params['attachment'] = $payload['attachments'];
        }

        $this->dispatch($params, 'Mailgun templated email sent successfully.');
    }

    /**
     * @throws RavenDeliveryException
     */
    private function sendWithFilesystemTemplate(mixed $notifiable, EmailNotification $emailNotification): void
    {
        $payload = $emailNotification->toMailgun($notifiable);
        $sender = config('raven.customizations.email.from');

        $scrollParams = $emailNotification->scroll->getParams();
        $template = $this->templateStrategy->resolve($scrollParams, $emailNotification->notificationContext);

        $params = array_filter([
            'from' => "{$sender['name']} <{$sender['address']}>",
            'to' => $payload['to'],
            'cc' => $payload['cc'],
            'bcc' => $payload['bcc'],
            'h:Reply-To' => $payload['replyTo'],
            'subject' => $template->subject,
            'html' => $template->html,
            'text' => $template->plainText,
        ], fn ($v) => $v !== null);

        if (! empty($payload['attachments'])) {
            $params['attachment'] = $payload['attachments'];
        }

        $this->dispatch($params, "Email with subject: $template->subject sent successfully.");
    }

    /**
     * @throws RavenDeliveryException
     */
    private function dispatch(array $params, string $successMessage): void
    {
        $domain = config('raven.providers.mailgun.domain');

        try {
            $response = $this->mailgun->messages()->send($domain, $params);

            Log::info($successMessage, [
                'MessageId' => method_exists($response, 'getId') ? $response->getId() : null,
            ]);
        } catch (Throwable $e) {
            Log::error('Mailgun error while sending email: '.$e->getMessage());
            throw new RavenDeliveryException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
