<?php

namespace ChijiokeIbekwe\Raven\Templates;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Library\TemplateCleaner;
use ChijiokeIbekwe\Raven\Notifications\EmailNotification;

class FilesystemTemplateStrategy implements TemplateStrategy
{
    public function resolve(array $params, NotificationContext $context): TemplateContent
    {
        $template_location = config('raven.customizations.templates_directory')
            .EmailNotification::EMAIL_FOLDER
            .$context->email_template_filename;

        $clean_html = TemplateCleaner::cleanFile($params, $template_location);

        return new TemplateContent(
            subject: TemplateCleaner::cleanText($params, $context->email_subject),
            html: $clean_html,
            plainText: strip_tags($clean_html),
        );
    }
}
