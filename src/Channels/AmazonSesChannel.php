<?php

namespace ChijiokeIbekwe\Raven\Channels;

use Aws\Exception\AwsException;
use Aws\Ses\Exception\SesException;
use Aws\Ses\SesClient;
use Aws\SesV2\SesV2Client;
use ChijiokeIbekwe\Raven\Exceptions\RavenDeliveryException;
use ChijiokeIbekwe\Raven\Notifications\EmailNotification;
use ChijiokeIbekwe\Raven\Templates\TemplateStrategy;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class AmazonSesChannel
{
    private SesClient $sesClient;

    private SesV2Client $sesV2Client;

    private TemplateStrategy $templateStrategy;

    public function __construct()
    {
        $this->sesClient = app(SesClient::class);
        $this->sesV2Client = app(SesV2Client::class);
        $this->templateStrategy = app(TemplateStrategy::class);
    }

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $emailNotification): void
    {
        if (! $emailNotification instanceof EmailNotification) {
            throw new RavenDeliveryException('AmazonSesChannel requires an EmailNotification notification');
        }

        if ($emailNotification->notificationContext->email_template_id) {
            $this->sendWithStoredTemplate($notifiable, $emailNotification);
        } else {
            $this->sendWithFilesystemTemplate($notifiable, $emailNotification);
        }
    }

    /**
     * Send using a stored SES template via the SES v2 API.
     *
     * @throws RavenDeliveryException
     */
    private function sendWithStoredTemplate(mixed $notifiable, EmailNotification $emailNotification): void
    {
        $route = $emailNotification->resolveRecipientRoute($notifiable);
        $sender = config('raven.customizations.email.from');
        $context = $emailNotification->notificationContext;
        $scroll = $emailNotification->scroll;

        $destination = ['ToAddresses' => [$route]];

        if (! empty($scroll->getCcs())) {
            $destination['CcAddresses'] = array_map(
                fn ($key, $value) => is_string($key) ? $key : $value,
                array_keys($scroll->getCcs()),
                array_values($scroll->getCcs())
            );
        }

        if (! empty($scroll->getBccs())) {
            $destination['BccAddresses'] = array_map(
                fn ($key, $value) => is_string($key) ? $key : $value,
                array_keys($scroll->getBccs()),
                array_values($scroll->getBccs())
            );
        }

        $params = [
            'FromEmailAddress' => "{$sender['name']} <{$sender['address']}>",
            'Destination' => $destination,
            'Content' => [
                'Template' => [
                    'TemplateName' => $context->email_template_id,
                    'TemplateData' => json_encode($scroll->getParams()),
                ],
            ],
        ];

        if ($replyTo = $scroll->getReplyTo()) {
            $params['ReplyToAddresses'] = [$replyTo];
        }

        try {
            $result = $this->sesV2Client->sendEmail($params);

            Log::info('SES templated email sent successfully.', [
                'MessageId' => $result->get('MessageId'),
            ]);
        } catch (AwsException $e) {
            Log::error('SES error while sending templated email: '.$e->getAwsErrorMessage());
            throw new RavenDeliveryException($e->getAwsErrorMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Send using a filesystem template via the SES v1 raw email API.
     *
     * @throws RavenDeliveryException
     */
    private function sendWithFilesystemTemplate(mixed $notifiable, EmailNotification $emailNotification): void
    {
        $email = $emailNotification->toAmazonSes($notifiable);

        $sender = config('raven.customizations.email.from');
        $email->setFrom($sender['address'], $sender['name']);

        $params = $emailNotification->scroll->getParams();
        $template = $this->templateStrategy->resolve($params, $emailNotification->notificationContext);

        $email->Subject = $template->subject;
        $email->Body = $template->html;
        $email->AltBody = $template->plainText;

        if (! $email->preSend()) {
            Log::error('Failed sending mail: '.$email->ErrorInfo);
            throw new RavenDeliveryException($email->ErrorInfo);
        } else {
            $message = $email->getSentMIMEMessage();
        }

        try {
            $result = $this->sesClient->sendRawEmail([
                'RawMessage' => [
                    'Data' => $message,
                ],
            ]);

            $statusCode = $result['@metadata']['statusCode'];

            if ($statusCode === 200) {
                Log::info("Email with subject: $template->subject sent successfully.", [
                    'MessageId' => $result->get('MessageId'),
                ]);
            } else {
                Log::error("Email with subject: $template->subject not sent successfully.", [
                    'MessageId' => $result->get('MessageId'),
                    'StatusCode' => $statusCode,
                    'ResponseMetadata' => $result['@metadata'],
                ]);
                throw new RavenDeliveryException(
                    "SES mail delivery failed with status code $statusCode"
                );
            }
        } catch (SesException $e) {
            Log::error('SES error while sending email: '.$e->getAwsErrorMessage());
            throw new RavenDeliveryException($e->getAwsErrorMessage(), $e->getCode(), $e);
        }
    }
}
