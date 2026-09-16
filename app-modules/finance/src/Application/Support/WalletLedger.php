<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Support;

use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Support\InvalidFields;
use Modules\Finance\Domain\Models\Settlement;
use Modules\Finance\Domain\Models\WalletTransaction;
use Modules\Finance\Infrastructure\SettlementReceiptPdf;

final class WalletLedger
{
    public function __construct(
        private readonly SettlementReceiptPdf $pdf,
        private readonly RecordsAudit $audit,
    ) {}

    public function balance(int $repId): int
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

    /**
     * @return array{settlement: Settlement, new_balance: int, replayed: bool}
     */
    public function settle(
        object $actor,
        int $repId,
        int $channelId,
        int $amount,
        string $operationNo,
        ?string $operatedAt,
        bool $withPdf,
    ): array {
        $existing = Settlement::query()
            ->where('operation_no', $operationNo)
            ->first();
        if ($existing !== null) {
            return [
                'settlement' => $existing,
                'new_balance' => $this->balance($repId),
                'replayed' => true,
            ];
        }

        if ($amount > $this->balance($repId)) {
            InvalidFields::throw(['amount' => 'finance.settle_exceeds_wallet']);
        }

        $settlement = Settlement::query()->create([
            'supply_channel_id' => $channelId,
            'rep_id' => $repId,
            'amount' => $amount,
            'operation_no' => $operationNo,
            'operated_at' => $operatedAt ?? now(),
            'receipt_pdf_url' => '',
        ]);

        WalletTransaction::query()->create([
            'supply_channel_id' => $channelId,
            'rep_id' => $repId,
            'type' => 'settled',
            'amount' => $amount,
            'settlement_id' => $settlement->id,
            'operation_no' => $operationNo,
        ]);

        if ($withPdf) {
            $url = $this->pdf->write((int) $settlement->id, [
                'rep_id' => $repId,
                'amount' => $amount,
                'operation_no' => $operationNo,
            ]);
            $settlement->forceFill(['receipt_pdf_url' => $url])->save();
        }

        $this->audit->record('finance.settle', $actor, 'settlement', (int) $settlement->id, [
            'rep_id' => $repId,
            'amount' => $amount,
        ], $channelId);

        return [
            'settlement' => $settlement,
            'new_balance' => $this->balance($repId),
            'replayed' => false,
        ];
    }
}
