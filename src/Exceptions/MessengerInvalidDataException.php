<?php

namespace ChijiokeIbekwe\Messenger\Exceptions;
use Exception;

class MessengerInvalidDataException extends Exception
{
    protected $code = 422;
}