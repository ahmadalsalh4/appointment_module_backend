<?php

namespace App\Support\Exceptions;

class AppointmentForbiddenException extends AppointmentException
{
    public function __construct(string $message = 'Bu randevuyu düzenleme yetkiniz yok')
    {
        parent::__construct($message, 403);
    }
}