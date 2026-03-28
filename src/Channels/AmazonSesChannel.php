<?php

namespace ChijiokeIbekwe\Raven\Channels;

use Aws\Ses\Exception\SesException;
use Aws\Ses\SesClient;
use ChijiokeIbekwe\Raven\Exceptions\RavenDeliveryException;
use ChijiokeIbekwe\Raven\Exceptions\RavenTemplateNotFoundException;
use ChijiokeIbekwe\Raven\Library\TemplateCleaner;
use ChijiokeIbekwe\Raven\Notifications\EmailNotificationSender;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use SendGrid;
use Throwable;

class AmazonSesChannel
{
    private SesClient $sesClient;

    private SendGrid $sendGrid;

    public function __construct()
    {
        $this->sesClient = app(SesClient::class);
        $this->sendGrid = app(SendGrid::class);
    }

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $emailNotification): void
    {
        if (! $emailNotification instanceof EmailNotificationSender) {
            throw new RavenDeliveryException('AmazonSesChannel requires an EmailNotificationSender notification');
        }

        $email = $emailNotification->toAmazonSes($notifiable);

        $sender = config('raven.customizations.mail.from');
        $email->setFrom($sender['address'], $sender['name']);

        $template_source = config('raven.providers.ses.template_source');
        if ($template_source !== 'sendgrid') {
            Log::error("Template source $template_source not currently supported");
            throw new RavenDeliveryException("Template source $template_source not currently supported");
        }

        $templateId = $emailNotification->notificationContext->email_template_id;
        $params = $emailNotification->scroll->getParams();

        $template_response = $this->getSendGridTemplateContent($templateId);

        $clean_html = TemplateCleaner::cleanText($params, $template_response['html_content']);
        $clean_plain = TemplateCleaner::cleanText($params, $template_response['plain_content']);
        $clean_subject = TemplateCleaner::cleanText($params, $template_response['subject']);

        $email->Subject = $clean_subject;
        $email->Body = $clean_html;
        $email->AltBody = $clean_plain;

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
                Log::info("Email with subject: $clean_subject sent successfully.", [
                    'MessageId' => $result->get('MessageId'),
                ]);
            } else {
                Log::error("Email with subject: $clean_subject not sent successfully.", [
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

    /**
     * @throws RavenTemplateNotFoundException
     */
    private function getSendGridTemplateContent(string $templateId): array
    {
        try {
            $response = $this->sendGrid->client->templates()->_($templateId)->get();

            if (! ($response->statusCode() >= 200 && $response->statusCode() < 300)) {
                throw new RavenTemplateNotFoundException(
                    'SendGrid template fetch failed with status code '.$response->statusCode()
                );
            }

            $body_json = $response->body();
            $body_arr = json_decode($body_json, true);
            $subject = $body_arr['versions'][0]['subject'];
            $html_content = $body_arr['versions'][0]['html_content'];
            $plain_content = $body_arr['versions'][0]['plain_content'];

            return [
                'subject' => $subject,
                'html_content' => $html_content,
                'plain_content' => $plain_content,
            ];
        } catch (RavenTemplateNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed fetching SendGrid template: '.$e->getMessage());
            throw new RavenTemplateNotFoundException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
