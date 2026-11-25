<?php

declare(strict_types=1);

namespace Datacircle\OpggMcp;

use RuntimeException;

/**
 * An answer of 4xx (an unknown slug, a wrong argument): its message is the API's own words.
 */
class ApiError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message, $status);
    }
}
