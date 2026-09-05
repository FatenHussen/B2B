<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Modules\Core\Domain\Exceptions\DomainException;

final class BulkConfirmSubOrders
{
    public function __construct(private readonly ConfirmSubOrder $confirm) {}

    /**
     * @param  array{ids: list<int>}  $data
     * @return array{confirmed: list<int>, failed: list<array{id: int, error: string}>}
     */
    public function __invoke(object $actor, array $data): array
    {
        $ids = array_slice(array_map('intval', $data['ids']), 0, 50);
        $confirmed = [];
        $failed = [];

        foreach ($ids as $id) {
            try {
                $this->confirm->__invoke($actor, $id);
                $confirmed[] = $id;
            } catch (DomainException $e) {
                $failed[] = ['id' => $id, 'error' => $e->errorCode];
            }
        }

        return ['confirmed' => $confirmed, 'failed' => $failed];
    }
}
