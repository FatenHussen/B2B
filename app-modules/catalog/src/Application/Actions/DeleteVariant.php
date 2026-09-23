<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductVariant;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;

final class DeleteVariant
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @return array{deleted: true}
     */
    public function __invoke(object $actor, int $productId, int $variantId): array
    {
        $product = Product::query()->findOrFail($productId);
        $variant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereKey($variantId)
            ->first();

        if ($variant === null) {
            throw DomainException::of(ErrorCode::NotFound);
        }

        $variant->delete();

        $this->audit->record('catalog.variant.delete', $actor, 'product_variant', $variantId, [
            'after' => ['deleted' => true],
        ], Tenant::currentId());

        return ['deleted' => true];
    }
}
