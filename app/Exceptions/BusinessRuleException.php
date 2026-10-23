<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A domain rule was violated. The message is user-facing and the code is the
 * HTTP status to answer with (422 by default).
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $data  Extra payload returned in `data`.
     */
    public function __construct(string $message, int $status = 422, public readonly array $data = [], public readonly ?string $errorCode = null)
    {
        parent::__construct($message, $status);
    }

    public function status(): int
    {
        return $this->getCode();
    }
}
