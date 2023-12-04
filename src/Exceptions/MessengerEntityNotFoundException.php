<?php

namespace ChijiokeIbekwe\Messenger\Exceptions;
use Exception;

class MessengerEntityNotFoundException extends Exception
{
    protected $code = 404;
}