<?php

namespace App\Support\Exceptions;

class AppointmentCategoryMismatchException extends AppointmentException
{
    public function __construct(string $message = 'Bu personel seçilen hizmeti sunmamaktadır.')
    {
        parent::__construct($message, 422);
    }
}