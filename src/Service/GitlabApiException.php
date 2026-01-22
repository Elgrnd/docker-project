<?php

namespace App\Service;

class GitlabApiException extends \RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
