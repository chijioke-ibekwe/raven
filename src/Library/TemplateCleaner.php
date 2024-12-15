<?php

namespace ChijiokeIbekwe\Raven\Library;

class TemplateCleaner
{
    public static function cleanFile(array $params, string $file_location): string
    {
        $param_keys = [];
        $param_values = [];

        foreach ($params as $key => $value) {
            $param_keys[] = '{{' . $key . '}}';
            $param_values[] = $value;
        }

        $template_content = file_get_contents($file_location);

        return str_replace($param_keys, $param_values, $template_content);
    }

    public static function cleanText(array $params, string $template): string
    {
        $param_keys = [];
        $param_values = [];

        foreach ($params as $key => $value) {
            $param_keys[] = '{{' . $key . '}}';
            $param_values[] = $value;
        }

        return str_replace($param_keys, $param_values, $template);
    }
}