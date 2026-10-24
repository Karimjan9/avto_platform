<?php

namespace App\Exceptions;

use RuntimeException;

class SmsDeliveryException extends RuntimeException
{
    public function __construct()
    {
        // Never attach provider responses, credentials, phone numbers or OTPs.
        parent::__construct('SMS yuborilmadi. Birozdan keyin qayta urinib ko‘ring.');
    }
}
