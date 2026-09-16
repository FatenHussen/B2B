<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Queries;

use Illuminate\Http\Request;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\Settlement;

final class ListRepWithdrawals
{
    public function __construct(private readonly RepDirectory $reps) {}

    /**
     * @return array{rows: list<array{operation_no: string, amount: int, operated_at: string|null}>, total: int}
     */
    public function __invoke(object $user, Request $request): array
    {
        $repUserId = (int) $user->getAuthIdentifier();
        $channelId = $this->reps->channelIdForUser($repUserId);
        if ($channelId === null) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        $from = $request->query('date_from');
        $to = $request->query('date_to');

        return Tenant::as($channelId, function () use ($repUserId, $from, $to): array {
            $query = Settlement::query()->where('rep_id', $repUserId)->orderBy('id');
            if (is_string($from) && $from !== '') {
                $query->whereDate('operated_at', '>=', $from);
            }
            if (is_string($to) && $to !== '') {
                $query->whereDate('operated_at', '<=', $to);
            }

            $rows = $query->get();

            return [
                'rows' => $rows->map(fn (Settlement $row): array => [
                    'operation_no' => (string) $row->operation_no,
                    'amount' => (int) $row->amount,
                    'operated_at' => $row->operated_at?->timezone('Asia/Damascus')->toDateString()
                        ?? $row->created_at?->timezone('Asia/Damascus')->toDateString(),
                ])->all(),
                'total' => (int) $rows->sum(fn (Settlement $row) => (int) $row->amount),
            ];
        });
    }
}
