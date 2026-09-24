<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\CreditApprovalRequest;

final class DecideCreditApproval
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @param  array{decision: string, reason?: string|null}  $data
     * @return array{id: int, status: string}
     */
    public function __invoke(object $actor, int $id, array $data): array
    {
        $channelId = (int) Tenant::currentId();

        return DB::transaction(function () use ($actor, $id, $data, $channelId): array {
            $row = CreditApprovalRequest::query()->whereKey($id)->lockForUpdate()->first();
            if ($row === null) {
                throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
            }

            if ($row->status !== 'pending') {
                throw DomainException::of(ErrorCode::IllegalTransition, __('finance.illegal_transition'));
            }

            $approve = $data['decision'] === 'approve';
            $status = $approve ? 'approved' : 'rejected';

            $row->forceFill([
                'status' => $status,
                'reason' => $data['reason'] ?? null,
                'decided_by' => (int) $actor->getAuthIdentifier(),
                'decided_at' => now(),
            ])->save();

            $this->audit->record('finance.credit_approval.decide', $actor, 'credit_approval_request', (int) $row->id, [
                'after' => [
                    'decision' => $data['decision'],
                    'status' => $status,
                    'reason' => $data['reason'] ?? null,
                    'retailer_id' => (int) $row->retailer_id,
                ],
            ], $channelId);

            return ['id' => (int) $row->id, 'status' => $status];
        });
    }
}
