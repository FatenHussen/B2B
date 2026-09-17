<?php

declare(strict_types=1);

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One exchange rate between two currencies, in force from `effective_from` until
 * `effective_to` (open when null).
 *
 * `rate` is a bigInteger at `Money::FX_SCALE` — 1.0 is 1_000_000 — and there is no scale
 * column, deliberately (CLAUDE.md rule 7). Nothing here divides.
 *
 * @property int $id
 * @property int $from_currency_id
 * @property int $to_currency_id
 * @property int $rate
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string|null $source
 * @property int|null $entered_by
 */
class FxRate extends Model
{
    protected $table = 'fx_rates';

    protected $fillable = [
        'from_currency_id',
        'to_currency_id',
        'rate',
        'effective_from',
        'effective_to',
        'source',
        'entered_by',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function fromCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency_id');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function toCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }
}
