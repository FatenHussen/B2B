<?php

declare(strict_types=1);

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Reference\Domain\Enums\RefStatus;

class Currency extends Model
{
    /**
     * `iso` and `code` both hold the ISO 4217 code and both are unique. `iso` is the
     * contract — every catalog response names it — and `code` is being retired in BE-R08.
     * Until then both are fillable, because a row written through `code` alone would
     * leave `iso` null and the API would answer with a currency that has no ISO code.
     */
    protected $fillable = ['code', 'iso', 'name', 'symbol', 'decimals', 'is_base', 'status'];

    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'decimals' => 'integer',
            'status' => RefStatus::class,
        ];
    }
}
