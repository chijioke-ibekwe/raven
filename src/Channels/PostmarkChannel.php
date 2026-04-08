<?php

namespace ChijiokeIbekwe\Raven\Channels;

use ChijiokeIbekwe\Raven\Exceptions\RavenDeliveryException;
use ChijiokeIbekwe\Raven\Notifications\EmailNotification;
use ChijiokeIbekwe\Raven\Templates\TemplateStrategy;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Postmark\PostmarkClient;
use Throwable;

class PostmarkChannel
{
    private PostmarkClient $postmarkClient;

    private TemplateStrategy $templateStrategy;

    public function __construct()
    {
        $this->postmarkClient = app(PostmarkClient::class);
        $this->templateStrategy = app(TemplateStrategy::class);
    }

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $emailNotification): void
    {
        if (! $emailNotification instanceof EmailNotification) {
            throw new RavenDeliveryException('PostmarkChannel requires an EmailNotification notification');
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
        $payload = $emailNotification->toPostmark($notifiable);
        $sender = config('raven.customizations.email.from');
        $context = $emailNotification->notificationContext;
        $templateIdOrAlias = $context->email_template_id;

        // Postmark accepts numeric template IDs as int and aliases as string.
        if (is_string($templateIdOrAlias) && ctype_digit($templateIdOrAlias)) {
            $templateIdOrAlias = (int) $templateIdOrAlias;
        }

        try {
            $response = $this->postmarkClient->sendEmailWithTemplate(
                from: "{$sender['name']} <{$sender['address']}>",
                to: $payload['to'],
                templateIdOrAlias: $templateIdOrAlias,
                templateModel: $emailNotification->scroll->getParams(),
                replyTo: $payload['replyTo'],
                cc: $payload['cc'],
                bcc: $payload['bcc'],
                attachments: $payload['attachments'] ?: null,
            );

            Log::info('Postmark templated email sent successfully.', [
                'MessageID' => $response->messageid ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('Postmark error while sending templated email: '.$e->getMessage());
            throw new RavenDeliveryException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws RavenDeliveryException
     */
    private function sendWithFilesystemTemplate(mixed $notifiable, EmailNotification $emailNotification): void
    {
        $payload = $emailNotification->toPostmark($notifiable);
        $sender = config('raven.customizations.email.from');

        $params = $emailNotification->scroll->getParams();
        $template = $this->templateStrategy->resolve($params, $emailNotification->notificationContext);

        try {
            $response = $this->postmarkClient->sendEmail(
                from: "{$sender['name']} <{$sender['address']}>",
                to: $payload['to'],
                subject: $template->subject,
                htmlBody: $template->html,
                textBody: $template->plainText,
                replyTo: $payload['replyTo'],
                cc: $payload['cc'],
                bcc: $payload['bcc'],
                attachments: $payload['attachments'] ?: null,
            );

            Log::info("Email with subject: $template->subject sent successfully.", [
                'MessageID' => $response->messageid ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('Postmark error while sending email: '.$e->getMessage());
            throw new RavenDeliveryException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
