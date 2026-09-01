<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Modules\Catalog\Domain\Models\RetailerShortage;
use Modules\Core\Contracts\RetailerShoppingContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DeleteShortage
{
    public function __construct(private readonly RetailerShoppingContext $shopping) {}

    /**
     * @return array{success: true}
     */
    public function __invoke(object $user, int $id): array
    {
        $ctx = $this->shopping->for($user);
        $row = RetailerShortage::query()
            ->whereKey($id)
            ->where('retailer_id', $ctx['retailer_id'])
            ->first();

        if ($row === null) {
            throw new NotFoundHttpException;
        }

        $row->delete();

        return ['success' => true];
    }
}
