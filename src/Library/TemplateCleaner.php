<?php

namespace ChijiokeIbekwe\Raven\Library;

use ChijiokeIbekwe\Raven\Exceptions\RavenTemplateNotFoundException;

class TemplateCleaner
{
    /**
     * @throws RavenTemplateNotFoundException
     */
    public static function cleanFile(array $params, string $file_location): string
    {
        $template_content = file_get_contents($file_location);

        if ($template_content === false) {
            throw new RavenTemplateNotFoundException("Template file not found in: {$file_location}");
        }

        return self::cleanText($params, $template_content);
    }

    public static function cleanText(array $params, string $template): string
    {
        $param_keys = [];
        $param_values = [];

        foreach ($params as $key => $value) {
            $param_keys[] = '{{'.$key.'}}';
            $param_values[] = $value;
        }

        return str_replace($param_keys, $param_values, $template);
    }
}
