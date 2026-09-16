<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Finance\Application\Support\WalletLedger;

final class RecordRepWithdrawal
{
    public function __construct(
        private readonly RepDirectory $reps,
        private readonly WalletLedger $wallet,
    ) {}

    /**
     * @param  array{amount: int, operation_no: string, operated_at: string}  $data
     * @return array{remaining_balance: int}
     */
    public function __invoke(object $user, array $data): array
    {
        $repUserId = (int) $user->getAuthIdentifier();
        $channelId = $this->reps->channelIdForUser($repUserId);
        if ($channelId === null) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        return Tenant::as($channelId, fn (): array => DB::transaction(function () use ($user, $repUserId, $channelId, $data): array {
            $result = $this->wallet->settle(
                $user,
                $repUserId,
                $channelId,
                (int) $data['amount'],
                (string) $data['operation_no'],
                (string) $data['operated_at'],
                false,
            );

            return ['remaining_balance' => $result['new_balance']];
        }));
    }
}
