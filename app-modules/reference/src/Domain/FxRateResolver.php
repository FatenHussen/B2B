<?php

declare(strict_types=1);

namespace Modules\Reference\Domain;

use DateTimeInterface;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Reference\Domain\Models\FxRate;

/**
 * Which rate is in force for a currency pair at a given moment.
 *
 * The question this class answers is not "what is the latest rate" but "which of several
 * overlapping rows applies", and the two differ the moment anyone corrects a mistake.
 */
final class FxRateResolver
{
    /**
     * The prevailing rate for a pair, at `$at` or now.
     *
     * **The rule: the prevailing rate is the most recently created row among the rows in
     * force at that moment for that pair.**
     *
     * "In force" is `effective_from <= $at` and (`effective_to` is null or `> $at`).
     * Windows are half-open, so no instant belongs to two windows by their boundaries
     * alone — an overlap is only ever a real overlap, never an off-by-one at a seam.
     *
     * Among those, `id DESC` wins, which is deliberately "the last row entered". That is
     * the right answer *here* and the wrong answer as a general rule, so the distinction
     * matters: the candidates have already been filtered to rows that are genuinely in
     * force right now. Choosing the newest of those is choosing the latest correction to a
     * currently-applicable rate. What was rejected is `id DESC` applied to the whole
     * table, which would let a row whose window has not opened, or has closed, override
     * one that is actually running.
     *
     * Ordering by `effective_from DESC` instead was considered and rejected: it makes a
     * correction impossible. Enter a wrong rate for a window that has not ended, and the
     * fix — a new row covering the remainder — loses to the original whenever the original
     * starts earlier, which it always does. Preventing overlap outright was also rejected:
     * it forbids the legitimate correction too, and needs a lock and a transaction on
     * every write to enforce.
     *
     * @throws DomainException when no row is in force — a conversion never invents a rate
     */
    public function prevailing(int $fromCurrencyId, int $toCurrencyId, ?DateTimeInterface $at = null): FxRate
    {
        $rate = $this->find($fromCurrencyId, $toCurrencyId, $at);

        if ($rate === null) {
            throw new DomainException(
                __('reference.fx_rate_missing'),
                'fx_rate_missing',
                422,
            );
        }

        return $rate;
    }

    /**
     * The same lookup, returning null instead of throwing.
     */
    public function find(int $fromCurrencyId, int $toCurrencyId, ?DateTimeInterface $at = null): ?FxRate
    {
        $moment = $at ?? now();

        return FxRate::query()
            ->where('from_currency_id', $fromCurrencyId)
            ->where('to_currency_id', $toCurrencyId)
            ->where('effective_from', '<=', $moment)
            ->where(function ($query) use ($moment) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $moment);
            })
            ->orderByDesc('id')
            ->first();
    }
}
