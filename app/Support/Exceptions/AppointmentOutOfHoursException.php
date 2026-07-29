<?php

namespace App\Support\Exceptions;

class AppointmentOutOfHoursException extends AppointmentException
{
    public function __construct(string $message = 'Seçilen saat aralığı personelin mesai saatleri (09:00-12:00, 13:00-17:00) dışındadır.')
    {
        parent::__construct($message, 422);
    }
}