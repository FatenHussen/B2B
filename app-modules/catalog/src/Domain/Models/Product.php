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
        'length_mm',
        'width_mm',
        'height_mm',
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

    /**
     * The channel scope is lifted on this relation and on `category()`, with the reason
     * here (rule 10, BE-C12). A product's brand and category are on the product's own
     * channel by construction — `supply_channel_id` is written from the same tenant that
     * wrote the product — so whatever constrained the product query has already
     * constrained what these can load. On `/app/*` there is no tenant: the retailer and
     * rep product queries lift the scope on the root and filter by the caller's channels,
     * and an eager load that re-applied Brand's strict scope threw on every branded
     * product. Lifting it here widens nothing and removes that failure once, where the
     * relation is defined, rather than at every `with('brand')`.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withoutGlobalScope('channel');
    }

    /** See `brand()`. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withoutGlobalScope('channel');
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

    /**
     * @return HasMany<ProductRetailerGroup, $this>
     */
    public function retailerGroups(): HasMany
    {
        return $this->hasMany(ProductRetailerGroup::class);
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
