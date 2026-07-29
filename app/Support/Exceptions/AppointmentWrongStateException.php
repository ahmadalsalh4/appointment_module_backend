<?php

namespace App\Support\Exceptions;

class AppointmentWrongStateException extends AppointmentException
{
    public function __construct(string $message = 'Sadece onay bekleyen randevular düzenlenebilir.')
    {
        parent::__construct($message, 422);
    }
}