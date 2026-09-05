<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface CreatesPickingList
{
    /**
     * @param  array{
     *     sub_order_id: int,
     *     channel_id: int,
     *     warehouse_id: int,
     *     lines: list<array{product_id: int, variant_id: int|null, qty: int}>
     * }  $payload
     */
    public function create(array $payload): int;

    public function idForSubOrder(int $subOrderId): ?int;
}
