<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use Modules\Core\Domain\Enums\ErrorCode;
use RuntimeException;

class DomainException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 400,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Raise a code from the DOC-08 map (BE-C02).
     *
     * The status comes from the code rather than the call site, so a code cannot
     * arrive at a client under two different statuses depending on who threw it.
     *
     * @param  array<string, mixed>  $details
     */
    public static function of(ErrorCode $code, ?string $message = null, array $details = []): self
    {
        return new self($message ?? $code->message(), $code->value, $code->status(), $details);
    }
}
