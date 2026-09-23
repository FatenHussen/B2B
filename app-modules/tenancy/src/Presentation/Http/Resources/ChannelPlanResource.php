<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tenancy\Domain\Models\ChannelPlan;

/**
 * @mixin ChannelPlan
 */
final class ChannelPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ChannelPlan $plan */
        $plan = $this->resource;

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'key' => $plan->key,
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'currency_id' => $plan->currency_id,
            'limits' => $plan->limits,
            'features' => $plan->features ?? [],
            'on_exceed' => $plan->on_exceed->value,
            'trial_days' => $plan->trial_days,
            'is_public' => $plan->is_public,
            'status' => $plan->status->value,
        ];
    }
}
