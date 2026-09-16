<?php

declare(strict_types=1);

namespace Modules\Core\Domain;

final readonly class DualApprovalDecision
{
    public function __construct(
        public bool $execute,
        public ?int $approvalRequestId = null,
    ) {}

    public static function pending(int $approvalRequestId): self
    {
        return new self(false, $approvalRequestId);
    }

    public static function proceed(?int $approvalRequestId = null): self
    {
        return new self(true, $approvalRequestId);
    }
}
