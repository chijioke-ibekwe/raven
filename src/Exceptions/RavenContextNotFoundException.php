<?php

namespace ChijiokeIbekwe\Raven\Exceptions;

use Exception;

class RavenContextNotFoundException extends Exception
{
    protected $code = 404;
}
