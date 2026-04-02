<?php

namespace ChijiokeIbekwe\Raven\Templates;

use ChijiokeIbekwe\Raven\Data\NotificationContext;

interface TemplateStrategy
{
    public function resolve(array $params, NotificationContext $context): TemplateContent;
}
