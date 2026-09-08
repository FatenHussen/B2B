<?php

declare(strict_types=1);

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Reference\Domain\Enums\RefStatus;

class Currency extends Model
{
    /**
     * `code` is gone as of BE-R08; `iso` is the only ISO 4217 column and the one the
     * catalog names in every currency response.
     *
     * Two fields are deliberately absent. `status` moves only through EP-AD-043F under
     * rule 8, like every other reference entity. And `is_base` is never writable through
     * the API at all: it is the unit every stored `bigInteger` amount is denominated in
     * under rule 7, it appears in no contract, and letting a request change it would
     * reinterpret the whole ledger with no data migration. `is_display_currency` is the
     * switchable one, and it is not the same fact.
     */
    protected $fillable = ['iso', 'name', 'symbol', 'decimals', 'is_display_currency'];

    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'is_display_currency' => 'boolean',
            'decimals' => 'integer',
            'status' => RefStatus::class,
        ];
    }
}
