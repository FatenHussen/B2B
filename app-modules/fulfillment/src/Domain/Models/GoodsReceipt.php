<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    protected $fillable = ['warehouse_id', 'source', 'reference_no', 'status'];

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }
}
