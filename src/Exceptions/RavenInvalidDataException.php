<?php

namespace ChijiokeIbekwe\Raven\Exceptions;
use Exception;

class RavenInvalidDataException extends Exception
{
    protected $code = 422;
}