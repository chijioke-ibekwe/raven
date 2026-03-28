<?php

namespace ChijiokeIbekwe\Raven\Exceptions;

use Exception;

class RavenTemplateNotFoundException extends Exception
{
    protected $code = 404;
}
