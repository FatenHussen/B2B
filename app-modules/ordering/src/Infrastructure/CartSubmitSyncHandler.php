<?php

declare(strict_types=1);

namespace Modules\Ordering\Infrastructure;

use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Contracts\SyncOperationHandler;
use Modules\Ordering\Application\Actions\SubmitRepCartSection;
use Modules\Ordering\Application\Actions\SubmitRetailerCart;

final class CartSubmitSyncHandler implements SyncOperationHandler
{
    public function __construct(
        private readonly SubmitRetailerCart $retailerSubmit,
        private readonly SubmitRepCartSection $repSubmit,
        private readonly RetailerShoppingContext $shopping,
        private readonly RepSellingContext $selling,
    ) {}

    public function handles(string $type): bool
    {
        return in_array($type, ['cart.submit', 'order', 'cart.section.submit'], true);
    }

    public function apply(object $user, string $type, array $payload, string $opId): array
    {
        if ($this->shopping->isRetailer($user) && in_array($type, ['cart.submit', 'order'], true)) {
            $result = ($this->retailerSubmit)($user, $payload);
            $id = isset($result['order']['id']) ? (int) $result['order']['id'] : null;

            return ['status' => 'applied', 'server_id' => $id, 'error' => null];
        }

        if ($this->selling->isRep($user)) {
            $retailerId = (int) ($payload['retailer_id'] ?? 0);
            if ($retailerId < 1) {
                return ['status' => 'failed', 'server_id' => null, 'error' => 'validation_failed'];
            }
            $result = ($this->repSubmit)($user, $retailerId, $payload);
            $id = isset($result['sub_order']['id']) ? (int) $result['sub_order']['id'] : null;

            return ['status' => 'applied', 'server_id' => $id, 'error' => null];
        }

        return ['status' => 'failed', 'server_id' => null, 'error' => 'unknown_type'];
    }
}
