<?php

namespace ChijiokeIbekwe\Raven\Channels;

use Aws\Ses\Exception\SesException;
use Aws\Ses\SesClient;
use ChijiokeIbekwe\Raven\Exceptions\RavenDeliveryException;
use ChijiokeIbekwe\Raven\Notifications\EmailNotification;
use ChijiokeIbekwe\Raven\Templates\TemplateStrategy;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class AmazonSesChannel
{
    private SesClient $sesClient;

    private TemplateStrategy $templateStrategy;

    public function __construct()
    {
        $this->sesClient = app(SesClient::class);
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

        $email = $emailNotification->toAmazonSes($notifiable);

        $sender = config('raven.customizations.mail.from');
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
