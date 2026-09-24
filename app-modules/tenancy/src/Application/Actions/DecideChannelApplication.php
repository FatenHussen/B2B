<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Actions;

use Illuminate\Support\Str;
use Modules\Core\Contracts\ChannelLimits;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Tenancy\Application\Services\ChannelApplicationLifecycle;
use Modules\Tenancy\Domain\ChannelLimitResolver;
use Modules\Tenancy\Domain\Enums\ChannelApplicationStatus;
use Modules\Tenancy\Domain\Models\ChannelApplication;
use Modules\Tenancy\Domain\Models\ChannelPlan;

/**
 * PA-04 — EP-AD-061. Maps the request to a lifecycle decision; the status write, the
 * row lock and the transaction live in ChannelApplicationLifecycle.
 */
final class DecideChannelApplication
{
    public function __construct(
        private readonly CreateChannel $createChannel,
        private readonly ChannelApplicationLifecycle $lifecycle,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{application_id: int, channel_id: int|null, status: string}
     */
    public function __invoke(ChannelApplication $application, array $data, object $actor): array
    {
        $decision = (string) $data['decision'];
        $reason = (string) $data['reason'];

        if ($decision === 'reject') {
            $decided = $this->lifecycle->decide($application, ChannelApplicationStatus::Rejected, $actor, $reason);

            return [
                'application_id' => (int) $decided->id,
                'channel_id' => null,
                'status' => $decided->status->value,
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
        $trialDays = (int) ($data['trial_days'] ?? $plan->trial_days ?? 0);

        $decided = $this->lifecycle->decide(
            $application,
            ChannelApplicationStatus::Provisioning,
            $actor,
            $reason,
            function (ChannelApplication $locked) use ($plan, $limits, $trialDays, $actor): int {
                $contact = $locked->contact ?? [];
                $created = ($this->createChannel)([
                    'name' => $locked->name,
                    'slug' => Str::slug($locked->name).'-'.Str::lower(Str::random(6)),
                    'legal_form' => $locked->legal_form ?? 'llc',
                    'cr_number' => $locked->cr_number ?? 'PENDING',
                    'documents' => $locked->documents ?? [],
                    'plan_id' => (int) $plan->id,
                    'billing_cycle' => 'monthly',
                    'trial_days' => $trialDays,
                    'limits' => $limits,
                    'governorate_ids' => array_map('intval', (array) ($contact['governorate_ids'] ?? [])),
                    'activity_type_ids' => array_map('intval', (array) ($contact['activity_type_ids'] ?? [])),
                    'zone_ids' => array_map('intval', (array) ($contact['zone_ids'] ?? [])),
                ], $actor);

                return (int) $created['id'];
            },
        );

        return [
            'application_id' => (int) $decided->id,
            'channel_id' => (int) $decided->channel_id,
            'status' => $decided->status->value,
        ];
    }
}
