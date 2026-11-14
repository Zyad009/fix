<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Headless Chrome could not render an invoice or receipt picture. The message
 * carries the renderer's output for the logs; users only see a generic reason.
 */
class DocumentImageException extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('Rendering the document image failed: '.$previous->getMessage(), 0, $previous);
    }
}
