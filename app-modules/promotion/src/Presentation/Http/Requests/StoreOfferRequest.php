<?php

declare(strict_types=1);

namespace Modules\Promotion\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;
use Modules\Promotion\Domain\Enums\OfferStatus;
use Modules\Promotion\Domain\Enums\OfferType;

final class StoreOfferRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::enum(OfferType::class)],
            'media' => ['nullable', 'array'],
            'description' => ['nullable', 'string'],
            'components' => ['nullable', 'array'],
            'components.*.product_id' => ['required', 'integer'],
            'components.*.qty' => ['required', 'integer', 'min:1'],
            'rules' => ['nullable', 'array'],
            'rules.buy_qty' => ['nullable', 'integer', 'min:1'],
            'rules.get_qty' => ['nullable', 'integer', 'min:1'],
            'rewards' => ['nullable', 'array'],
            'rewards.product_id' => ['nullable', 'integer'],
            'rewards.qty' => ['nullable', 'integer', 'min:1'],
            'rewards.discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'rewards.discount_amount' => ['nullable', 'integer', 'min:0'],
            'targeting' => ['nullable', 'array'],
            'targeting.scope' => ['nullable', 'in:all,zones,groups,retailers'],
            'targeting.activity_type_ids' => ['nullable', 'array'],
            'targeting.zone_ids' => ['nullable', 'array'],
            'targeting.group_ids' => ['nullable', 'array'],
            'targeting.retailer_ids' => ['nullable', 'array'],
            'constraints' => ['nullable', 'array'],
            'constraints.starts_at' => ['nullable', 'date'],
            'constraints.ends_at' => ['nullable', 'date'],
            'constraints.total_qty' => ['nullable', 'integer', 'min:0'],
            'constraints.per_retailer_max' => ['nullable', 'integer'],
            'constraints.per_order_max' => ['nullable', 'integer'],
            'constraints.min_invoice_value' => ['nullable', 'integer', 'min:0'],
            'constraints.min_items' => ['nullable', 'integer'],
            'stackable' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(OfferStatus::class)],
        ];
    }
}
