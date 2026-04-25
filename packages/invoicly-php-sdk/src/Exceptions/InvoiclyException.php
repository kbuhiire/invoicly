<?php

namespace Invoicly\Exceptions;

use RuntimeException;

class InvoiclyException extends RuntimeException
{
    private int $httpStatus;

    /** @var array<string, mixed> */
    private array $errors;

    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(string $message, int $httpStatus = 0, array $errors = [])
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->errors = $errors;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function isUnauthorized(): bool
    {
        return $this->httpStatus === 401;
    }

    public function isForbidden(): bool
    {
        return $this->httpStatus === 403;
    }

    public function isNotFound(): bool
    {
        return $this->httpStatus === 404;
    }

    public function isValidationError(): bool
    {
        return $this->httpStatus === 422;
    }

    public function isRateLimited(): bool
    {
        return $this->httpStatus === 429;
    }
}
