<?php

namespace App\Exceptions;

use RuntimeException;

class ShopifyHttpException extends RuntimeException
{
    public int $status;
    public ?string $body;

    public function __construct(int $status, string $message, ?string $body = null)
    {
        parent::__construct($message);
        $this->status = $status;
        $this->body   = $body;
    }
}
