<?php

namespace App\Support\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base class for appointment-controller business-rule violations. The
 * outer handler maps each subclass to its HTTP status + message, so the
 * controller body no longer has to encode outcomes as string sentinels
 * ('conflict', 'wrong_state', 'no_staff', ...).
 */
abstract class AppointmentException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}