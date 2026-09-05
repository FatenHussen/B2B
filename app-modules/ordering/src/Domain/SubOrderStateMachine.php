<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain;

use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Ordering\Domain\Enums\SubOrderStatus;

final class SubOrderStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const MAP = [
        'confirm' => ['pending'],
        'reject' => ['pending'],
        'cancel' => ['pending', 'confirmed', 'assigned', 'accepted', 'processing', 'postponed'],
        'retailer_cancel' => ['pending'],
        'edit_lines' => ['pending', 'confirmed'],
        'assign' => ['confirmed', 'postponed'],
        'reassign' => ['assigned', 'accepted'],
        'schedule' => ['confirmed', 'assigned', 'accepted'],
        'rep_accept' => ['assigned'],
        'rep_reject' => ['assigned'],
        'processing' => ['confirmed', 'assigned', 'accepted'],
        'awaiting_handover' => ['processing', 'accepted', 'confirmed', 'assigned'],
        'on_the_way' => ['accepted', 'awaiting_handover', 'processing', 'confirmed', 'assigned'],
        'delivered' => ['on_the_way'],
        'undelivered' => ['on_the_way'],
        'postpone' => ['confirmed', 'assigned', 'accepted', 'on_the_way'],
    ];

    /**
     * @var array<string, SubOrderStatus>
     */
    private const TARGET = [
        'confirm' => SubOrderStatus::Confirmed,
        'reject' => SubOrderStatus::Rejected,
        'cancel' => SubOrderStatus::Cancelled,
        'retailer_cancel' => SubOrderStatus::Cancelled,
        'assign' => SubOrderStatus::Assigned,
        'reassign' => SubOrderStatus::Assigned,
        'schedule' => SubOrderStatus::Postponed,
        'rep_accept' => SubOrderStatus::Accepted,
        'rep_reject' => SubOrderStatus::Confirmed,
        'processing' => SubOrderStatus::Processing,
        'awaiting_handover' => SubOrderStatus::AwaitingHandover,
        'on_the_way' => SubOrderStatus::OnTheWay,
        'delivered' => SubOrderStatus::Delivered,
        'undelivered' => SubOrderStatus::Undelivered,
        'postpone' => SubOrderStatus::Postponed,
    ];

    public function assert(SubOrderStatus $from, string $action): void
    {
        $allowed = self::MAP[$action] ?? [];
        if (! in_array($from->value, $allowed, true)) {
            throw new DomainException(__('ordering.illegal_transition'), 'illegal_transition', 409);
        }
    }

    public function target(string $action): SubOrderStatus
    {
        return self::TARGET[$action] ?? throw new DomainException(__('ordering.illegal_transition'), 'illegal_transition', 409);
    }

    /**
     * @return list<string>
     */
    public function allowed(SubOrderStatus $from, array $permissions = []): array
    {
        $out = [];
        $permMap = [
            'confirm' => 'sc.orders.confirm',
            'reject' => 'sc.orders.reject',
            'cancel' => 'sc.orders.cancel',
            'edit_lines' => 'sc.orders.edit_lines',
            'assign' => 'sc.orders.assign',
            'reassign' => 'sc.orders.reassign',
            'schedule' => 'sc.orders.schedule',
        ];

        foreach (self::MAP as $action => $froms) {
            if (! in_array($from->value, $froms, true)) {
                continue;
            }
            if (isset($permMap[$action]) && $permissions !== [] && ! in_array($permMap[$action], $permissions, true)) {
                continue;
            }
            if (in_array($action, ['retailer_cancel', 'processing', 'awaiting_handover', 'on_the_way', 'delivered', 'undelivered', 'postpone', 'rep_accept', 'rep_reject'], true)) {
                continue;
            }
            $out[] = $action;
        }

        return array_values($out);
    }
}
