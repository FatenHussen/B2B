<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Support\Concerns\BelongsToChannel;
use Modules\Pricing\Domain\Enums\PriceType;

class ProductBasePrice extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'product_id',
        'currency_id',
        'type',
        'base_price',
        'cost_price',
        'tax_percent',
    ];

    protected function casts(): array
    {
        return [
            'type' => PriceType::class,
            'base_price' => 'integer',
            'cost_price' => 'integer',
            'tax_percent' => 'integer',
        ];
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(ProductQtyTier::class, 'product_id', 'product_id')->orderBy('from_qty');
    }
}
