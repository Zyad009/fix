<?php

namespace App\Exceptions;

use LogicException;

/**
 * A maintenance-center-scoped model was queried or created with no maintenance center in context.
 * This is a programming error (fail closed), never a user error.
 */
class MissingMaintenanceCenterContextException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Maintenance-center-scoped data was accessed without a maintenance center context.');
    }
}
