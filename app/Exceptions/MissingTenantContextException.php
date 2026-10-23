<?php

namespace App\Exceptions;

use LogicException;

/**
 * A tenant-scoped model was queried or created with no tenant in context.
 * This is a programming error (fail closed), never a user error.
 */
class MissingTenantContextException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Tenant-scoped data was accessed without a tenant context.');
    }
}
