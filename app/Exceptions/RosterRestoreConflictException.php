<?php

namespace App\Exceptions;

use App\Data\Time\RosterRestoreAssessment;

class RosterRestoreConflictException extends RosterValidationException
{
    public function __construct(
        string $message,
        public RosterRestoreAssessment $assessment,
    ) {
        parent::__construct($message);
    }
}
