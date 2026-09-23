<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Actions;

use Modules\Core\Contracts\RecordsAudit;
use Modules\Tenancy\Application\Services\PlanLifecycle;
use Modules\Tenancy\Domain\Enums\PlanOnExceed;
use Modules\Tenancy\Domain\Enums\PlanStatus;
use Modules\Tenancy\Domain\Models\ChannelPlan;

/**
 * EP-AD-100D. Updating plan limits never rewrites existing channel_limits rows —
 * those were copied at provision time (EP-AD-100D note `d`).
 */
final class UpdatePlan
{
    public function __construct(
        private readonly RecordsAudit $audit,
        private readonly PlanLifecycle $lifecycle,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int}
     */
    public function __invoke(ChannelPlan $plan, array $data, object $actor): array
    {
        $reason = (string) $data['reason'];
        $before = [
            'name' => $plan->name,
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'limits' => $plan->limits,
        ];

        $fill = [];
        foreach (['name', 'price_monthly', 'price_yearly', 'currency_id', 'limits', 'features', 'trial_days', 'is_public'] as $field) {
            if (array_key_exists($field, $data)) {
                $fill[$field] = $data[$field];
            }
        }
        if (isset($data['on_exceed'])) {
            $fill['on_exceed'] = PlanOnExceed::from((string) $data['on_exceed']);
        }

        if ($fill !== []) {
            $plan->fill($fill);
            $plan->save();
        }

        if (isset($data['status'])) {
            $this->lifecycle->transition($plan, PlanStatus::from((string) $data['status']), $actor, $reason);
        }

        $this->audit->record(
            action: 'plan.updated',
            actor: $actor,
            subjectType: ChannelPlan::class,
            subjectId: (int) $plan->id,
            properties: [
                'reason' => $reason,
                'before' => $before,
                'after' => [
                    'name' => $plan->name,
                    'price_monthly' => $plan->price_monthly,
                    'price_yearly' => $plan->price_yearly,
                    'limits' => $plan->limits,
                ],
            ],
        );

        return ['id' => (int) $plan->id];
    }
}
