<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\Exception;

use Exception;
use Throwable;

abstract class EsReindexCommandException extends Exception
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
