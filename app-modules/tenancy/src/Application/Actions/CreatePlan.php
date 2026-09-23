<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Actions;

use Modules\Core\Contracts\RecordsAudit;
use Modules\Tenancy\Domain\Enums\PlanOnExceed;
use Modules\Tenancy\Domain\Enums\PlanStatus;
use Modules\Tenancy\Domain\Models\ChannelPlan;

final class CreatePlan
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int}
     */
    public function __invoke(array $data, object $actor): array
    {
        $plan = new ChannelPlan;
        $plan->fill([
            'key' => $data['key'],
            'name' => $data['name'],
            'price_monthly' => (int) $data['price_monthly'],
            'price_yearly' => (int) $data['price_yearly'],
            'currency_id' => (int) $data['currency_id'],
            'limits' => $data['limits'],
            'features' => $data['features'] ?? [],
            'on_exceed' => PlanOnExceed::from((string) $data['on_exceed']),
            'trial_days' => (int) ($data['trial_days'] ?? 0),
            'is_public' => (bool) ($data['is_public'] ?? true),
            'is_active' => true,
        ]);
        $plan->status = PlanStatus::Active;
        $plan->save();

        $this->audit->record(
            action: 'plan.created',
            actor: $actor,
            subjectType: ChannelPlan::class,
            subjectId: (int) $plan->id,
            properties: ['key' => $plan->key],
        );

        return ['id' => (int) $plan->id];
    }
}
