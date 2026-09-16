<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure;

use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RequestsDualApproval;
use Modules\Core\Domain\DualApprovalDecision;
use Modules\Core\Domain\Enums\DualApprovalStatus;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Models\DualApprovalRequest;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;

final class EloquentRequestsDualApproval implements RequestsDualApproval
{
    public function __construct(private readonly RecordsAudit $audit) {}

    public function gate(
        object $actor,
        string $permission,
        string $action,
        array $payload,
        ?int $approvalRequestId = null,
        ?string $approvalReason = null,
    ): DualApprovalDecision {
        $hash = $this->hash($payload);
        $actorId = (int) $actor->getAuthIdentifier();
        $channelId = (int) Tenant::currentId();

        if ($approvalRequestId === null) {
            $row = new DualApprovalRequest;
            $row->fill([
                'supply_channel_id' => $channelId,
                'permission' => $permission,
                'action' => $action,
                'payload' => $payload,
                'payload_hash' => $hash,
                'requester_id' => $actorId,
            ]);
            $row->forceFill(['status' => DualApprovalStatus::Pending])->save();

            $this->audit->record('dual.request', $actor, 'dual_approval_request', (int) $row->id, [
                'permission' => $permission,
                'action' => $action,
            ], $channelId);

            return DualApprovalDecision::pending((int) $row->id);
        }

        $row = DualApprovalRequest::query()->find($approvalRequestId);
        if ($row === null) {
            throw DomainException::of(ErrorCode::NotFound, __('core.not_found'));
        }

        if ($row->status !== DualApprovalStatus::Pending) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('core.request_not_pending'));
        }

        if ((int) $row->requester_id === $actorId) {
            throw DomainException::of(ErrorCode::SodViolation, __('core.sod_approver_is_creator'));
        }

        if ($row->permission !== $permission || $row->action !== $action || $row->payload_hash !== $hash) {
            throw DomainException::of(ErrorCode::StaleVersion, __('core.payload_mismatch'));
        }

        $reason = is_string($approvalReason) ? trim($approvalReason) : '';
        if ($reason === '') {
            InvalidFields::throw(['approval_reason' => 'core.approval_reason_required']);
        }

        $row->forceFill([
            'status' => DualApprovalStatus::Approved,
            'approver_id' => $actorId,
            'reason' => $reason,
        ])->save();

        $this->audit->record('dual.approve', $actor, 'dual_approval_request', (int) $row->id, [
            'reason' => $reason,
        ], $channelId);

        return DualApprovalDecision::proceed((int) $row->id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hash(array $payload): string
    {
        $normalized = $payload;
        unset($normalized['approval_request_id'], $normalized['approval_reason']);
        $this->sortRecursive($normalized);

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function sortRecursive(array &$value): void
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursive($item);
            }
        }
    }
}
