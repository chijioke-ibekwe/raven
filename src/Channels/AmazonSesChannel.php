<?php

namespace ChijiokeIbekwe\Raven\Channels;

use Aws\Ses\Exception\SesException;
use Aws\Ses\SesClient;
use ChijiokeIbekwe\Raven\Library\TemplateCleaner;
use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use SendGrid;

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
     *
     * @param mixed $notifiable
     * @param Notification $emailNotification
     * @return void
     * @throws Exception
     */
    public function send(mixed $notifiable, Notification $emailNotification): void
    {
        $email = $emailNotification->toAmazonSes($notifiable);

        $sender = config('raven.customizations.mail.from');
        $email->setFrom($sender['address'], $sender['name']);

        $template_source = config('raven.providers.ses.template_source');
        if($template_source !== 'sendgrid') {
            Log::error("Template source $template_source not currently supported");
            throw new Exception("Template source $template_source not currently supported");
        }

        $template_response = $this->getSendGridTemplateContent($emailNotification);

        $params = $emailNotification->scroll->getParams();
        $clean_html = TemplateCleaner::cleanText($params, $template_response['html_content']);
        $clean_plain = TemplateCleaner::cleanText($params, $template_response['plain_content']);
        $clean_subject = TemplateCleaner::cleanText($params, $template_response['subject']);

        $email->Subject = $clean_subject;
        $email->Body = $clean_html;
        $email->AltBody = $clean_plain;

        if (!$email->preSend()) {
            Log::error("Failed sending mail: " . $email->ErrorInfo);
            throw new Exception($email->ErrorInfo);
        } else {
            $message = $email->getSentMIMEMessage();
        }

        try {
            $result = $this->sesClient->sendRawEmail([
                'RawMessage' => [
                    'Data' => $message
                ]
            ]);

            $statusCode = $result['@metadata']['statusCode'];

            if ($statusCode === 200) {
                Log::info("Email with subject: $clean_subject sent successfully.", [
                    'MessageId' => $result->get('MessageId')
                ]);
            } else {
                Log::warning("Email with subject: $clean_subject not sent successfully.", [
                    'MessageId' => $result->get('MessageId'),
                    'StatusCode' => $statusCode,
                    'ResponseMetadata' => $result['@metadata'],
                ]);
            }

        } catch (SesException $error) {
            Log::error('SES error while sending email: ' . $error->getAwsErrorMessage());
        } catch (Exception $e) {
            Log::error('General error while sending email: ' . $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    private function getSendGridTemplateContent(Notification $emailNotification): array
    {
        try {
            $template_id = $emailNotification->notificationContext->email_template_id;
            $response = $this->sendGrid->client->templates()->_($template_id)->get();

            if(!($response->statusCode() >= '200' && $response->statusCode() < '300')) {
                throw new Exception("SendGrid server returned error response");
            }

            $body_json = $response->body();
            $body_arr = json_decode($body_json, true);
            $subject = $body_arr['versions'][0]['subject'];
            $html_content = $body_arr['versions'][0]['html_content'];
            $plain_content = $body_arr['versions'][0]['plain_content'];

            return [
                'subject' => $subject,
                'html_content' => $html_content,
                'plain_content' => $plain_content
            ];

        } catch (Exception $e) {
            Log::error("Failed sending mail: " . $e->getMessage());
            throw new Exception($e);
        }
    }
}