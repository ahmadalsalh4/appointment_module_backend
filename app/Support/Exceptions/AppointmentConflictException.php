<?php

namespace App\Support\Exceptions;

class AppointmentConflictException extends AppointmentException
{
    public function __construct(string $message = 'Bu saat aralığında personelin başka bir randevusu var.')
    {
        parent::__construct($message, 409);
    }
}