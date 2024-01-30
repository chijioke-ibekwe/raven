<?php

namespace ChijiokeIbekwe\Raven\Exceptions;
use Exception;

class RavenEntityNotFoundException extends Exception
{
    protected $code = 404;
}