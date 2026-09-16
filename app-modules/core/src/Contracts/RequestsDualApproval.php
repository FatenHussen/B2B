<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\Domain\DualApprovalDecision;

interface RequestsDualApproval
{
    /**
     * First call stores a pending request and does not execute.
     * A different actor resubmitting the same payload with the request id executes.
     *
     * @param  array<string, mixed>  $payload
     */
    public function gate(
        object $actor,
        string $permission,
        string $action,
        array $payload,
        ?int $approvalRequestId = null,
        ?string $approvalReason = null,
    ): DualApprovalDecision;
}
