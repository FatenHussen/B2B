<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\RetailerCreditLimit;

final class UpsertRetailerCredit
{
    public function __construct(
        private readonly RetailerDirectory $retailers,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{credit_limit: int, grace_days: int, on_exceed: string}  $data
     * @return array{retailer_id: int, credit_limit: int, on_exceed: string}
     */
    public function __invoke(object $actor, int $retailerId, array $data): array
    {
        if (! $this->retailers->exists($retailerId)) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        $channelId = (int) Tenant::currentId();
        $row = RetailerCreditLimit::query()->updateOrCreate(
            ['supply_channel_id' => $channelId, 'retailer_id' => $retailerId],
            [
                'credit_limit' => (int) $data['credit_limit'],
                'grace_days' => (int) $data['grace_days'],
                'on_exceed' => (string) $data['on_exceed'],
            ],
        );

        $this->audit->record('finance.credit_limit', $actor, 'retailer_credit_limit', (int) $row->id, [
            'retailer_id' => $retailerId,
            'after' => $data,
        ], $channelId);

        return [
            'retailer_id' => $retailerId,
            'credit_limit' => (int) $row->credit_limit,
            'on_exceed' => (string) $row->on_exceed,
        ];
    }
}
