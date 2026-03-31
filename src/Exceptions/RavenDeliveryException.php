<?php

namespace ChijiokeIbekwe\Raven\Exceptions;

use Exception;
use Throwable;

class RavenDeliveryException extends Exception
{
    protected $code = 502;

    /** @var array<int, array{recipient: mixed, exception: Throwable}> */
    private array $failures = [];

    /**
     * @param  array<int, array{recipient: mixed, exception: Throwable}>  $failures
     */
    public static function fromFailures(string $message, array $failures): self
    {
        $instance = new self($message);
        $instance->failures = $failures;

        return $instance;
    }

    /**
     * @return array<int, array{recipient: mixed, exception: Throwable}>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }
}
