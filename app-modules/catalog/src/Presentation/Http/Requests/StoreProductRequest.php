<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Core\Http\ApiFormRequest;

final class StoreProductRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:200'],
            'name_en' => ['nullable', 'string', 'max:200'],
            'sku' => ['required', 'string', 'max:80'],
            'brand_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'model_no' => ['nullable', 'string', 'max:80'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'short_description' => ['nullable', 'string'],
            'long_description' => ['nullable', 'string'],
            'specs' => ['nullable', 'array'],
            'specs.*.key' => ['required', 'string'],
            'specs.*.value' => ['required', 'string'],
            'media' => ['nullable', 'array'],
            'media.images' => ['nullable', 'array'],
            'media.primary' => ['nullable'],
            'media.video' => ['nullable'],
            'sale_unit_id' => ['nullable', 'integer'],
            'unit_factors' => ['nullable', 'array'],
            'unit_factors.*.from_unit_id' => ['required', 'integer'],
            'unit_factors.*.to_unit_id' => ['required', 'integer'],
            'unit_factors.*.factor' => ['required', 'integer', 'min:1'],
            'min_order_qty' => ['nullable', 'integer', 'min:1'],
            'order_multiple' => ['nullable', 'integer', 'min:1'],
            'weight_gram' => ['nullable', 'integer', 'min:0'],
            'pricing' => ['nullable', 'array'],
            'pricing.type' => ['required_with:pricing', 'in:simple,tiered'],
            'pricing.base_price' => ['required_with:pricing', 'integer', 'min:0'],
            'pricing.currency_id' => ['nullable', 'integer'],
            'pricing.tiers' => ['nullable', 'array'],
            'pricing.tiers.*.from' => ['required', 'integer', 'min:1'],
            'pricing.tiers.*.to' => ['nullable', 'integer'],
            'pricing.tiers.*.price' => ['required', 'integer', 'min:0'],
            'inventory' => ['nullable', 'array'],
            'inventory.tracked' => ['nullable', 'boolean'],
            'inventory.reorder_point' => ['nullable', 'integer', 'min:0'],
            'inventory.allow_backorder' => ['nullable', 'boolean'],
            'availability' => ['nullable', 'array'],
            'availability.zone_ids' => ['nullable', 'array'],
            'availability.zone_ids.*' => ['integer'],
            'availability.activity_type_ids' => ['nullable', 'array'],
            'availability.activity_type_ids.*' => ['integer'],
            'availability.retailer_group_ids' => ['nullable', 'array'],
            'availability.lead_time_days' => ['nullable', 'integer', 'min:0'],
            'marketing' => ['nullable', 'array'],
            'marketing.tags' => ['nullable', 'array'],
            'marketing.sliders' => ['nullable', 'array'],
            'marketing.priority' => ['nullable', 'integer'],
        ];
    }
}
