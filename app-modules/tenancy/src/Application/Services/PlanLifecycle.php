<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Services;

use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Tenancy\Domain\Enums\PlanStatus;
use Modules\Tenancy\Domain\Models\ChannelPlan;

/**
 * The one place a plan's status changes (rule 8). Syncs `is_active` so CreateChannel's
 * `exists:channel_plans,id,is_active,1` check keeps working until a follow-up removes it.
 */
final class PlanLifecycle
{
    public function __construct(private readonly RecordsAudit $audit) {}

    public function transition(ChannelPlan $plan, PlanStatus $to, object $actor, string $reason): ChannelPlan
    {
        $from = $plan->status;

        if ($from === $to) {
            return $plan;
        }

        if (! $this->allowed($from, $to)) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('tenancy.illegal_transition'));
        }

        $plan->status = $to;
        $plan->is_active = $to === PlanStatus::Active;
        $plan->save();

        $this->audit->record(
            action: 'plan.status_changed',
            actor: $actor,
            subjectType: ChannelPlan::class,
            subjectId: (int) $plan->id,
            properties: [
                'from' => $from->value,
                'to' => $to->value,
                'reason' => $reason,
            ],
        );

        return $plan->refresh();
    }

    private function allowed(PlanStatus $from, PlanStatus $to): bool
    {
        return match ($from) {
            PlanStatus::Active => $to === PlanStatus::Inactive,
            PlanStatus::Inactive => $to === PlanStatus::Active,
        };
    }
}
