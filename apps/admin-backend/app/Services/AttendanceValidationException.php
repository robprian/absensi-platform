<?php

namespace App\Services;

use RuntimeException;

/**
 * Domain validation failure for attendance. Carries a machine-readable code
 * so the API can distinguish denied permission, low accuracy, network failure,
 * and geofence rejection — never silently approving a failed verification.
 */
class AttendanceValidationException extends RuntimeException
{
    public readonly string $errorCode;

    public function __construct(string $errorCode, string $message)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message);
    }
}
