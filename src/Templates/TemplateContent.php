<?php

namespace ChijiokeIbekwe\Raven\Templates;

class TemplateContent
{
    public function __construct(
        public readonly string $subject,
        public readonly string $html,
        public readonly string $plainText,
    ) {}
}
