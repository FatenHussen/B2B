<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Actions;

use Illuminate\Support\Str;
use Modules\Core\Contracts\ChannelLimits;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Tenancy\Domain\ChannelLimitResolver;
use Modules\Tenancy\Domain\Enums\ChannelApplicationStatus;
use Modules\Tenancy\Domain\Models\ChannelApplication;
use Modules\Tenancy\Domain\Models\ChannelPlan;

final class DecideChannelApplication
{
    public function __construct(
        private readonly CreateChannel $createChannel,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{application_id: int, channel_id: int|null, status: string}
     */
    public function __invoke(ChannelApplication $application, array $data, object $actor): array
    {
        if ($application->status !== ChannelApplicationStatus::UnderReview) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('tenancy.illegal_transition'));
        }

        $decision = (string) $data['decision'];
        $reason = (string) $data['reason'];
        $actorId = method_exists($actor, 'getAuthIdentifier') ? (int) $actor->getAuthIdentifier() : null;

        if ($decision === 'reject') {
            $application->status = ChannelApplicationStatus::Rejected;
            $application->fill([
                'decided_by' => $actorId,
                'decided_at' => now(),
                'reason' => $reason,
            ]);
            $application->save();

            $this->audit->record('channel_application.rejected', $actor, ChannelApplication::class, (int) $application->id, [
                'reason' => $reason,
            ]);

            return [
                'application_id' => (int) $application->id,
                'channel_id' => null,
                'status' => ChannelApplicationStatus::Rejected->value,
            ];
        }

        if ($decision !== 'approve') {
            throw DomainException::of(ErrorCode::ValidationFailed, __('tenancy.illegal_transition'));
        }

        $plan = ChannelPlan::query()->findOrFail((int) $data['plan_id']);
        $limits = array_intersect_key($plan->limits ?? [], array_flip(ChannelLimits::KEYS));
        foreach (ChannelLimits::KEYS as $key) {
            $limits[$key] = (int) ($limits[$key] ?? ChannelLimitResolver::DEFAULTS[$key]);
        }

        $contact = $application->contact ?? [];
        $created = ($this->createChannel)([
            'name' => $application->name,
            'slug' => Str::slug($application->name).'-'.Str::lower(Str::random(6)),
            'legal_form' => $application->legal_form ?? 'llc',
            'cr_number' => $application->cr_number ?? 'PENDING',
            'documents' => $application->documents ?? [],
            'plan_id' => (int) $plan->id,
            'billing_cycle' => 'monthly',
            'trial_days' => (int) ($data['trial_days'] ?? $plan->trial_days ?? 0),
            'limits' => $limits,
            'governorate_ids' => array_map('intval', (array) ($contact['governorate_ids'] ?? [])),
            'activity_type_ids' => array_map('intval', (array) ($contact['activity_type_ids'] ?? [])),
            'zone_ids' => array_map('intval', (array) ($contact['zone_ids'] ?? [])),
        ], $actor);

        $application->status = ChannelApplicationStatus::Provisioning;
        $application->fill([
            'decided_by' => $actorId,
            'decided_at' => now(),
            'reason' => $reason,
            'channel_id' => $created['id'],
        ]);
        $application->save();

        $this->audit->record('channel_application.approved', $actor, ChannelApplication::class, (int) $application->id, [
            'reason' => $reason,
            'channel_id' => $created['id'],
        ]);

        return [
            'application_id' => (int) $application->id,
            'channel_id' => (int) $created['id'],
            'status' => ChannelApplicationStatus::Provisioning->value,
        ];
    }
}
