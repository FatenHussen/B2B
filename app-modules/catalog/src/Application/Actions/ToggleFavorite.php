<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Modules\Catalog\Domain\Models\RetailerProductFavorite;
use Modules\Catalog\Application\Support\VisibleCatalogQuery;
use Modules\Core\Contracts\RetailerShoppingContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ToggleFavorite
{
    public function __construct(private readonly RetailerShoppingContext $shopping) {}

    /**
     * @return array{is_favorite: bool}
     */
    public function __invoke(object $user, int $productId): array
    {
        $ctx = $this->shopping->for($user);
        $visible = VisibleCatalogQuery::products($ctx)->whereKey($productId)->exists();
        if (! $visible) {
            throw new NotFoundHttpException;
        }

        $existing = RetailerProductFavorite::query()
            ->where('retailer_id', $ctx['retailer_id'])
            ->where('product_id', $productId)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return ['is_favorite' => false];
        }

        RetailerProductFavorite::query()->create([
            'retailer_id' => $ctx['retailer_id'],
            'product_id' => $productId,
        ]);

        return ['is_favorite' => true];
    }
}
