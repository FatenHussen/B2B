<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\Settlement;
use Modules\Finance\Domain\Models\WalletTransaction;
use Modules\Finance\Infrastructure\SettlementReceiptPdf;

final class SettleRepWallet
{
    public function __construct(
        private readonly RepDirectory $reps,
        private readonly SettlementReceiptPdf $pdf,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{amount: int, operation_no: string}  $data
     * @return array{receipt_pdf_url: string, new_balance: int}
     */
    public function __invoke(object $actor, int $repId, array $data): array
    {
        $channelId = (int) Tenant::currentId();
        if (! $this->reps->belongsToChannel($repId, $channelId)) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        $amount = (int) $data['amount'];

        return DB::transaction(function () use ($actor, $repId, $channelId, $amount, $data): array {
            $balance = $this->balance($repId);
            if ($amount > $balance) {
                InvalidFields::throw(['amount' => 'finance.settle_exceeds_wallet']);
            }

            $settlement = Settlement::query()->create([
                'supply_channel_id' => $channelId,
                'rep_id' => $repId,
                'amount' => $amount,
                'operation_no' => (string) $data['operation_no'],
                'receipt_pdf_url' => '',
            ]);

            WalletTransaction::query()->create([
                'supply_channel_id' => $channelId,
                'rep_id' => $repId,
                'type' => 'settled',
                'amount' => $amount,
                'settlement_id' => $settlement->id,
                'operation_no' => (string) $data['operation_no'],
            ]);

            $url = $this->pdf->write((int) $settlement->id, [
                'rep_id' => $repId,
                'amount' => $amount,
                'operation_no' => (string) $data['operation_no'],
            ]);
            $settlement->forceFill(['receipt_pdf_url' => $url])->save();

            $this->audit->record('finance.settle', $actor, 'settlement', (int) $settlement->id, [
                'rep_id' => $repId,
                'amount' => $amount,
            ], $channelId);

            return [
                'receipt_pdf_url' => $url,
                'new_balance' => $this->balance($repId),
            ];
        });
    }

    private function balance(int $repId): int
    {
        $collected = (int) WalletTransaction::query()
            ->where('rep_id', $repId)
            ->where('type', 'collected')
            ->sum('amount');
        $settled = (int) WalletTransaction::query()
            ->where('rep_id', $repId)
            ->where('type', 'settled')
            ->sum('amount');

        return $collected - $settled;
    }
}
