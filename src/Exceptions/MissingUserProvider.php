<?php

namespace AgedNerd\Masquerade\Exceptions;

use Throwable;

class MissingUserProvider extends \Exception
{
    public function __construct(string $guard, string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('Missing user provider for guard %s', $guard), $code, $previous);
    }
}
