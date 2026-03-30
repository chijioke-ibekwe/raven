<?php

namespace ChijiokeIbekwe\Raven\Templates;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Exceptions\RavenTemplateNotFoundException;
use ChijiokeIbekwe\Raven\Library\TemplateCleaner;
use Illuminate\Support\Facades\Log;
use SendGrid;
use Throwable;

class SendGridTemplateStrategy implements TemplateStrategy
{
    public function __construct(private readonly SendGrid $sendGrid) {}

    public function resolve(array $params, NotificationContext $context): TemplateContent
    {
        $template = $this->fetchTemplate($context->email_template_id);

        return new TemplateContent(
            subject: TemplateCleaner::cleanText($params, $template['subject']),
            html: TemplateCleaner::cleanText($params, $template['html_content']),
            plainText: TemplateCleaner::cleanText($params, $template['plain_content']),
        );
    }

    /**
     * @throws RavenTemplateNotFoundException
     */
    private function fetchTemplate(string $templateId): array
    {
        try {
            $response = $this->sendGrid->client->templates()->_($templateId)->get();

            if (! ($response->statusCode() >= 200 && $response->statusCode() < 300)) {
                throw new RavenTemplateNotFoundException(
                    'SendGrid template fetch failed with status code '.$response->statusCode()
                );
            }

            $body_arr = json_decode($response->body(), true);

            return [
                'subject' => $body_arr['versions'][0]['subject'],
                'html_content' => $body_arr['versions'][0]['html_content'],
                'plain_content' => $body_arr['versions'][0]['plain_content'],
            ];
        } catch (RavenTemplateNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed fetching SendGrid template: '.$e->getMessage());
            throw new RavenTemplateNotFoundException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
