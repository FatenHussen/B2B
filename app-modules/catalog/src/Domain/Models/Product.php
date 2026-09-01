<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Core\Support\Concerns\BelongsToChannel;

class Product extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'name_ar',
        'name_en',
        'sku',
        'brand_id',
        'category_id',
        'model_no',
        'barcode',
        'status',
        'short_description',
        'long_description',
        'sale_unit_id',
        'min_order_qty',
        'order_multiple',
        'weight_gram',
        'tracked',
        'reorder_point',
        'allow_backorder',
        'lead_time_days',
        'priority',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'tracked' => 'boolean',
            'allow_backorder' => 'boolean',
            'tags' => 'array',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function specs(): HasMany
    {
        return $this->hasMany(ProductSpec::class)->orderBy('order');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('order');
    }

    public function unitFactors(): HasMany
    {
        return $this->hasMany(ProductUnitFactor::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(ProductZone::class);
    }

    public function activityTypes(): HasMany
    {
        return $this->hasMany(ProductActivityType::class);
    }

    public function sliderTags(): HasMany
    {
        return $this->hasMany(ProductSliderTag::class);
    }

    public function variantAxes(): HasMany
    {
        return $this->hasMany(ProductVariantAxis::class)->orderBy('order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
