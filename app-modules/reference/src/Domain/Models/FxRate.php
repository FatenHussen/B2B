<?php

declare(strict_types=1);

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One exchange rate for one currency pair over one window of time.
 *
 * `rate` is a `bigInteger` at the fixed 10^6 scale — `Money::FX_UNIT` is 1.0. There is no
 * scale column and must never be one: see CLAUDE.md rule 7.
 *
 * Reference data owned by the platform, so no channel scope. Windows are half-open:
 * `effective_from` is inclusive, `effective_to` exclusive, and a null `effective_to` means
 * the rate is still running.
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
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function fromCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency_id');
    }

    public function toCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }
}
