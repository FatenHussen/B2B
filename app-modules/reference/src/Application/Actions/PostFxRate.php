<?php

declare(strict_types=1);

namespace Modules\Reference\Application\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\InvalidFields;
use Modules\Reference\Domain\Models\FxRate;

/**
 * BE-R09 — EP-AD-040. Post a rate from `currency_id` into the base currency, effective
 * from a moment, closing the rate that was in force before it.
 *
 * `rate` is an integer at `Money::FX_SCALE`; this class never divides, multiplies or
 * rounds it. It is stored as sent and read as stored. Existing orders keep the rate they
 * froze at submission (BR-AD-19) — nothing here touches them.
 */
final class PostFxRate
{
    public function __construct(
        private readonly ReferenceDirectory $refs,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{currency_id: int, rate: int, effective_from: string, source?: string|null, reason?: string|null}  $data
     */
    public function __invoke(array $data, ?object $actor): FxRate
    {
        $baseId = $this->refs->defaultCurrencyId();
        if ($baseId === null) {
            throw DomainException::of(ErrorCode::NotFound, __('reference.not_found'));
        }

        $currencyId = (int) $data['currency_id'];
        if ($currencyId === $baseId) {
            InvalidFields::throw(['currency_id' => 'reference.fx_rate_base_currency']);
        }

        // In the app timezone before it touches the database: the bulk `update()` below
        // hands the value to PDO as-is, while `create()` converts through the cast. Left
        // in the caller's offset, the closing time would land three hours off the
        // opening time of the rate that replaces it.
        $from = Carbon::parse((string) $data['effective_from'])->setTimezone((string) config('app.timezone', 'UTC'));

        return DB::transaction(function () use ($data, $actor, $baseId, $currencyId, $from): FxRate {
            // Close the open rate for this pair at the new one's start, so the history
            // reads as consecutive intervals and `FxRateResolver::find()` has one answer.
            FxRate::query()
                ->where('from_currency_id', $currencyId)
                ->where('to_currency_id', $baseId)
                ->whereNull('effective_to')
                ->where('effective_from', '<', $from)
                ->update(['effective_to' => $from]);

            $row = FxRate::query()->create([
                'from_currency_id' => $currencyId,
                'to_currency_id' => $baseId,
                'rate' => (int) $data['rate'],
                'effective_from' => $from,
                'effective_to' => null,
                'source' => $data['source'] ?? 'manual',
                'entered_by' => $actor !== null && method_exists($actor, 'getAuthIdentifier') ? (int) $actor->getAuthIdentifier() : null,
            ]);

            $this->audit->record('ref.fx_rates.create', $actor, 'fx_rate', (int) $row->id, [
                'after' => [
                    'currency_id' => $currencyId,
                    'rate' => (int) $data['rate'],
                    'effective_from' => $from->toIso8601String(),
                ],
                'reason' => $data['reason'] ?? null,
            ]);

            return $row;
        });
    }
}
