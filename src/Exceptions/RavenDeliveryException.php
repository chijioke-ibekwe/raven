<?php

namespace ChijiokeIbekwe\Raven\Exceptions;

use Exception;

class RavenDeliveryException extends Exception
{
    protected $code = 502;
}
