<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Queries;

use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;

final class TrackRetailerOrder
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly RepDirectory $reps,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user, int $id): array
    {
        $ctx = $this->shopping->for($user);
        $sub = SubOrder::query()->with('events')->where('retailer_id', $ctx['retailer_id'])->find($id);
        if ($sub === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }

        $rep = null;
        if ($sub->status === SubOrderStatus::OnTheWay && $sub->rep_id) {
            $rep = [
                'name' => $this->reps->displayName((int) $sub->rep_id),
                'phone' => AppUser::query()->whereKey($sub->rep_id)->value('phone'),
                'lat' => null,
                'lng' => null,
            ];
        }

        return [
            'stages' => $sub->events->map(fn ($e) => [
                'key' => $e->stage,
                'label' => $e->stage,
                'at' => $e->at?->timezone('Asia/Damascus')->toIso8601String(),
            ])->all(),
            'rep' => $rep,
            'eta_minutes' => null,
        ];
    }
}
