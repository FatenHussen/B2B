<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Modules\Catalog\Application\Support\VisibleCatalogQuery;
use Modules\Catalog\Domain\Models\RetailerShortage;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Support\InvalidFields;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AddShortage
{
    public function __construct(private readonly RetailerShoppingContext $shopping) {}

    /**
     * @param  array{product_id: int, note?: string|null}  $data
     * @return array{id: int}
     */
    public function __invoke(object $user, array $data): array
    {
        $ctx = $this->shopping->for($user);
        $productId = (int) $data['product_id'];
        if (! VisibleCatalogQuery::products($ctx)->whereKey($productId)->exists()) {
            InvalidFields::throw(['product_id' => 'catalog.product_not_visible']);
        }

        $row = RetailerShortage::query()->create([
            'retailer_id' => $ctx['retailer_id'],
            'product_id' => $productId,
            'note' => $data['note'] ?? null,
        ]);

        return ['id' => (int) $row->id];
    }
}
