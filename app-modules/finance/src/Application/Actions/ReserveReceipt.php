<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use Modules\Core\Contracts\ReceiptNumberReserver;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;

final class ReserveReceipt
{
    public function __construct(
        private readonly RepDirectory $reps,
        private readonly ReceiptNumberReserver $receipts,
    ) {}

    /**
     * @return array{receipt_no: string}
     */
    public function __invoke(object $user): array
    {
        $repUserId = (int) $user->getAuthIdentifier();
        $channelId = $this->reps->channelIdForUser($repUserId);
        if ($channelId === null) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        return Tenant::as($channelId, fn (): array => [
            'receipt_no' => $this->receipts->reserveFor($channelId, $repUserId),
        ]);
    }
}
